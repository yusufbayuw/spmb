<?php

namespace App\Services;

use App\Models\OnlineExamCategory;
use App\Models\OnlineExamQuestion;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class OnlineExamQuestionSpreadsheetService
{
    public const HEADERS = [
        'type', 'prompt', 'category_code', 'difficulty', 'competency_tag',
        'points', 'is_active', 'choices_json', 'prompts_json', 'grading_json',
    ];

    private const MAX_ROWS = 500;

    /**
     * @return array{imported:int,total:int}
     */
    public function import(string $storedPath, int $unitId, User $actor): array
    {
        $unit = $this->authorizedUnit($unitId, $actor);

        if (strtolower(pathinfo($storedPath, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw ValidationException::withMessages(['file' => 'Hanya file XLSX yang didukung.']);
        }

        $disk = Storage::disk('local');
        // Only a private upload from the dedicated Filament directory may be read/deleted.
        if (! str_starts_with($storedPath, 'imports/online-exam-questions/')
            || str_contains($storedPath, '..') || str_contains($storedPath, '\\')) {
            throw ValidationException::withMessages(['file' => 'Lokasi berkas impor tidak valid.']);
        }

        $absolutePath = $disk->path($storedPath);
        if (! is_file($absolutePath) || filesize($absolutePath) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'File tidak ditemukan atau melebihi 5 MB.']);
        }

        $reader = new Reader();
        $opened = false;
        $rows = [];
        $seen = [];
        $errors = [];
        $dataCount = 0;
        $categories = OnlineExamCategory::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->get()->keyBy('code');

        try {
            $reader->open($absolutePath);
            $opened = true;

            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;
                foreach ($sheet->getRowIterator() as $sheetRow) {
                    $values = $sheetRow->toArray();
                    $line++;

                    if ($line === 1) {
                        $headers = array_map(fn ($cell): string =>
                            strtolower(trim((string) $cell)), $values);
                        if ($headers !== self::HEADERS) {
                            throw ValidationException::withMessages([
                                'file' => 'Header XLSX tidak sesuai template. Download template terbaru tanpa mengubah urutan kolom.',
                            ]);
                        }

                        continue;
                    }

                    if (collect($values)->every(fn ($cell) => blank($cell))) {
                        continue;
                    }

                    $dataCount++;
                    if ($dataCount > self::MAX_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => 'Maksimal '.self::MAX_ROWS.' soal dalam satu unggahan.',
                        ]);
                    }

                    try {
                        $attributes = $this->parseRow($values, $categories);
                        $signature = hash('sha256', $attributes['type'].'|'.$attributes['prompt'].'|'.($attributes['online_exam_category_id'] ?? ''));
                        if (isset($seen[$signature])) {
                            throw new \InvalidArgumentException('Soal duplikat di dalam file.');
                        }
                        $seen[$signature] = true;
                        $rows[] = $attributes;
                    } catch (Throwable $exception) {
                        if (count($errors) < 20) {
                            $errors[] = 'Baris '.$line.': '.$exception->getMessage();
                        }
                    }
                }

                break; // Only the named first worksheet contains question data.
            }

            if ($dataCount === 0) {
                throw ValidationException::withMessages(['file' => 'XLSX tidak memiliki soal untuk diimport.']);
            }

            if ($errors !== []) {
                throw ValidationException::withMessages([
                    'file' => implode("\n", $errors).' Tidak ada soal yang disimpan.',
                ]);
            }

            // Validate every row before the first write. The model repeats its
            // validation in the transaction to avoid bypassing business rules.
            foreach ($rows as $index => $attributes) {
                try {
                    app(OnlineExamQuestionValidator::class)->validate(new OnlineExamQuestion($attributes));
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'file' => 'Baris '.($index + 2).': '.collect($exception->errors())->flatten()->first(),
                    ]);
                }
            }

            DB::transaction(function () use ($rows, $unit): void {
                foreach ($rows as $attributes) {
                    // Prevent accidental replay of the same import into this
                    // unit. Existing questions are never overwritten.
                    if (OnlineExamQuestion::query()
                        ->where('unit_id', $unit->id)
                        ->where('type', $attributes['type'])
                        ->where('prompt', $attributes['prompt'])
                        ->where('online_exam_category_id', $attributes['online_exam_category_id'])
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'file' => 'Soal sudah ada di unit ini; impor dibatalkan seluruhnya. Tidak ada soal yang ditimpa.',
                        ]);
                    }

                    OnlineExamQuestion::create($attributes + ['unit_id' => $unit->id]);
                }
            }, 3);

            app(AuditTrail::class)->record(
                'online_exam.questions_imported',
                $unit,
                actor: $actor,
                unitId: $unit->id,
                metadata: ['count' => count($rows)],
                description: 'Petugas mengimpor bank soal Tes Online melalui XLSX.',
            );

            return ['imported' => count($rows), 'total' => $dataCount];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'file' => 'XLSX tidak dapat diproses. Periksa format atau isian lalu coba lagi.',
            ]);
        } finally {
            if ($opened) {
                $reader->close();
            }
            $disk->delete($storedPath);
        }
    }

    /** @return array{path:string,filename:string} */
    public function generate(int $unitId, User $actor, bool $template = false): array
    {
        $unit = $this->authorizedUnit($unitId, $actor);
        $disk = Storage::disk('local');
        $path = 'exports/online-exam-questions/'.Str::uuid().'.xlsx';
        $disk->makeDirectory('exports/online-exam-questions');
        $absolutePath = $disk->path($path);

        $writer = new Writer();
        $writer->openToFile($absolutePath);

        try {
            $writer->getCurrentSheet()->setName('Bank Soal');
            $writer->addRow(Row::fromValues(self::HEADERS));

            if ($template) {
                foreach ($this->examples() as $example) {
                    $writer->addRow(Row::fromValues($example));
                }
            } else {
                $categories = OnlineExamCategory::query()->where('unit_id', $unit->id)
                    ->pluck('code', 'id');
                foreach (OnlineExamQuestion::query()->where('unit_id', $unit->id)
                    ->orderBy('id')->cursor() as $question) {
                    $writer->addRow(Row::fromValues([
                        $question->type, $question->prompt,
                        $categories[$question->online_exam_category_id] ?? '',
                        $question->difficulty ?? 'medium',
                        $question->competency_tag ?? '',
                        (float) $question->points,
                        $question->is_active ? '1' : '0',
                        json_encode($question->options['choices'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        json_encode($question->options['prompts'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        json_encode($question->grading_config ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ]));
                }
            }

            $help = $writer->addNewSheetAndMakeItCurrent();
            $help->setName('Petunjuk');
            foreach ([
                ['TEMPLATE BANK SOAL TES ONLINE'],
                ['Unit:', $unit->code, $unit->name],
                ['Data disimpan untuk unit yang dipilih saja. Jangan mengubah urutan kolom sheet Bank Soal.'],
                ['Jenis soal:', implode(', ', array_keys(OnlineExamQuestion::TYPES))],
                ['Kategori gunakan KODE dari menu Kategori Bank Soal; kosong berarti tanpa kategori.'],
                ['Difficulty: easy / medium / hard. Points: angka 0.01–10000; aktif: 1 atau 0.'],
                ['choices_json: object kode pilihan ke label; prompts_json: object kode pernyataan ke teks.'],
                ['grading_json berisi kunci jawaban sensitif. Jangan bagikan file kepada peserta.'],
                ['Impor hanya menambah soal baru, tidak mengubah soal existing. Seluruh file dibatalkan bila ada kesalahan.'],
                ['Maksimal 500 soal dan 5 MB per file. Data contoh dalam template harus diganti/dihapus sebelum impor.'],
            ] as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        } finally {
            $writer->close();
        }

        return [
            'path' => $absolutePath,
            'filename' => ($template ? 'template' : 'bank-soal').'-tes-online-'.$unit->code.'.xlsx',
        ];
    }

    public function download(int $unitId, User $actor, bool $template = false): BinaryFileResponse
    {
        $result = $this->generate($unitId, $actor, $template);

        return response()->download(
            $result['path'],
            $result['filename'],
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    private function authorizedUnit(int $unitId, User $actor): Unit
    {
        abort_unless(app(OnlineExamAccess::class)->canManageUnit($actor, $unitId), 403);

        return Unit::query()->findOrFail($unitId);
    }

    private function parseRow(array $values, \Illuminate\Support\Collection $categories): array
    {
        if (count($values) > count(self::HEADERS)
            && collect(array_slice($values, count(self::HEADERS)))->contains(fn ($cell) => filled($cell))) {
            throw new \InvalidArgumentException('Terdapat kolom tambahan di luar template.');
        }

        $cells = array_map(function ($value): string {
            if (! is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Isian harus berupa teks atau angka.');
            }

            return trim((string) $value);
        }, array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), ''));

        [$type, $prompt, $categoryCode, $difficulty, $tag, $points, $active, $choices, $prompts, $grading] = $cells;
        if (! isset(OnlineExamQuestion::TYPES[$type])
            || $prompt === '' || mb_strlen($prompt) > 10000
            || ! in_array($difficulty, ['easy', 'medium', 'hard'], true)
            || mb_strlen($tag) > 150
            || ! is_numeric($points) || ! is_finite((float) $points)
            || (float) $points < 0.01 || (float) $points > 10000
            || round((float) $points, 2) !== (float) $points
            || ! in_array($active, ['0', '1'], true)) {
            throw new \InvalidArgumentException('Jenis, pertanyaan, kesulitan, bobot, atau status aktif tidak valid.');
        }

        $categoryId = null;
        if ($categoryCode !== '') {
            $category = $categories->get(mb_strtoupper($categoryCode));
            if (! $category) {
                throw new \InvalidArgumentException('Kode kategori tidak ditemukan atau tidak aktif pada unit ini.');
            }

            $categoryId = $category->id;
        }

        try {
            $choicesData = json_decode($choices !== '' ? $choices : '{}', true, 512, JSON_THROW_ON_ERROR);
            $promptsData = json_decode($prompts !== '' ? $prompts : '{}', true, 512, JSON_THROW_ON_ERROR);
            $gradingData = json_decode($grading !== '' ? $grading : '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new \InvalidArgumentException('Kolom JSON tidak valid.');
        }

        if (! is_array($choicesData) || ! is_array($promptsData) || ! is_array($gradingData)) {
            throw new \InvalidArgumentException('Kolom JSON harus object atau array.');
        }

        return [
            'type' => $type,
            'prompt' => $prompt,
            'online_exam_category_id' => $categoryId,
            'difficulty' => $difficulty,
            'competency_tag' => $tag !== '' ? $tag : null,
            'points' => (float) $points,
            'is_active' => $active === '1',
            'options' => ['choices' => $choicesData, 'prompts' => $promptsData],
            'grading_config' => $gradingData,
        ];
    }

    private function examples(): array
    {
        return [
            ['single_choice', 'Contoh: Satuan gaya?', '', 'easy', 'fisika', 1, 1, '{"A":"Newton","B":"Joule"}', '{}', '{"correct":"A"}'],
            ['multiple_choice', 'Contoh: Besaran vektor?', '', 'medium', 'fisika', 2, 1, '{"A":"Gaya","B":"Suhu","C":"Kecepatan"}', '{}', '{"correct_options":["A","C"]}'],
            ['true_false', 'Contoh: Air membeku pada 0 C (1 atm).', '', 'easy', '', 1, 1, '{}', '{}', '{"correct":"1"}'],
            ['matching', 'Contoh: Pasangkan besaran dan satuannya.', '', 'medium', '', 2, 1, '{"A":"Newton","B":"Joule"}', '{"P":"Gaya","Q":"Energi"}', '{"pairs":{"P":"A","Q":"B"}}'],
            ['short_answer', 'Contoh: Simbol unsur besi?', '', 'medium', '', 1, 1, '{}', '{}', '{"accepted":["Fe"],"case_sensitive":false}'],
            ['numerical', 'Contoh: Gravitasi bumi (m/s2)?', '', 'medium', '', 1, 1, '{}', '{}', '{"value":9.8,"tolerance":0.2}'],
            ['essay', 'Contoh: Jelaskan hukum Newton II.', '', 'hard', '', 10, 1, '{}', '{}', '{}'],
        ];
    }
}
