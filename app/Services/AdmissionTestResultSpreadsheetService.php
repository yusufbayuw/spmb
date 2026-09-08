<?php

namespace App\Services;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdmissionTestResultSpreadsheetService
{
    private const HEADERS = [
        'RESULT_UUID',
        'TEST_UUID',
        'NO_REGISTRASI',
        'NAMA_PESERTA',
        'NILAI',
        'STATUS',
        'HASIL',
        'CATATAN',
    ];

    public function __construct(
        private readonly RegistrationWorkflowService $workflow,
    ) {}

    public function download(AdmissionTest $test, User $actor): BinaryFileResponse
    {
        $this->authorize($test, $actor);

        $path = tempnam(sys_get_temp_dir(), 'spmb-test-result-');

        if ($path === false) {
            throw ValidationException::withMessages([
                'download' => 'File sementara untuk export hasil tes tidak dapat dibuat.',
            ]);
        }

        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        $this->writeWorkbook($test, $actor, $xlsxPath);

        return response()
            ->download(
                $xlsxPath,
                'hasil-tes-'.Str::slug($test->name).'.xlsx',
                ['Cache-Control' => 'private, no-store'],
            )
            ->deleteFileAfterSend(true);
    }

    public function writeWorkbook(AdmissionTest $test, User $actor, string $path): void
    {
        $this->authorize($test, $actor);

        $results = AdmissionTestResult::query()
            ->with(['registration', 'admissionTest'])
            ->where('admission_test_id', $test->id)
            ->where('status', 'scheduled')
            ->whereHas('registration', fn (Builder $query): Builder => $query
                ->where('lifecycle_status', 'active')
                ->where('current_stage', 'tests'))
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('test_bookings')
                    ->whereColumn('test_bookings.registration_id', 'admission_test_results.registration_id')
                    ->whereColumn('test_bookings.admission_test_id', 'admission_test_results.admission_test_id')
                    ->whereNotNull('test_bookings.test_session_id');
            })
            ->get()
            ->sortBy(fn (AdmissionTestResult $result): string => (string) $result->registration?->registration_number)
            ->values();

        if ($results->isEmpty()) {
            throw ValidationException::withMessages([
                'admission_test_id' => 'Belum ada peserta terjadwal yang siap dicatat hasilnya untuk tes ini.',
            ]);
        }

        $writer = new Writer();
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Hasil Tes');
        $writer->addRow(Row::fromValues(self::HEADERS));

        foreach ($results as $result) {
            $writer->addRow(Row::fromValues([
                $result->uuid,
                $test->uuid,
                $result->registration?->registration_number,
                $result->registration?->full_name,
                $result->score !== null ? (float) $result->score : null,
                $this->statusLabel($result->status),
                $this->resultLabel($result->result),
                $result->notes,
            ]));
        }

        $instructionSheet = $writer->addNewSheetAndMakeItCurrent();
        $instructionSheet->setName('Petunjuk');
        $writer->addRows([
            Row::fromValues(['PETUNJUK PENGISIAN HASIL TES']),
            Row::fromValues(['Tes', $test->name]),
            Row::fromValues(['Jenis hasil', $test->result_type === 'pass_fail' ? 'Lulus / Tidak Lulus' : 'Nilai']),
            Row::fromValues(['Nilai kelulusan', $test->passing_score !== null ? (float) $test->passing_score : '-']),
            Row::fromValues([]),
            Row::fromValues(['Jangan mengubah', 'RESULT_UUID, TEST_UUID, NO_REGISTRASI, NAMA_PESERTA']),
            Row::fromValues(['STATUS yang diterima', 'TERJADWAL, SELESAI, TIDAK HADIR, DIBEBASKAN']),
            Row::fromValues(['HASIL yang diterima', 'BELUM DINILAI, LULUS, TIDAK LULUS']),
            Row::fromValues(['Tes bertipe Nilai', 'Isi NILAI. Jika nilai kelulusan dikonfigurasi, HASIL dihitung otomatis saat upload.']),
            Row::fromValues(['Hasil normal', 'STATUS boleh tetap TERJADWAL. Saat NILAI atau HASIL diisi, sistem otomatis memprosesnya sebagai SELESAI.']),
            Row::fromValues(['TIDAK HADIR', 'Ubah STATUS menjadi TIDAK HADIR. Hasil otomatis menjadi TIDAK LULUS.']),
            Row::fromValues(['DIBEBASKAN', 'Ubah STATUS menjadi DIBEBASKAN. Hasil otomatis menjadi LULUS.']),
            Row::fromValues(['Baris tanpa hasil', 'Biarkan STATUS TERJADWAL serta NILAI/HASIL kosong atau BELUM DINILAI. Baris tersebut tidak akan diubah.']),
            Row::fromValues(['Penting', 'Upload harus memakai file hasil download sistem. Seluruh baris divalidasi sebelum ada data yang disimpan.']),
        ]);

        $writer->close();
    }

    /**
     * @return array{test:AdmissionTest,updated:int,skipped:int}
     */
    public function import(string $path, User $actor): array
    {
        [$headers, $rows] = $this->readWorkbook($path);

        if ($headers !== self::HEADERS) {
            throw ValidationException::withMessages([
                'file' => 'Format kolom XLSX tidak sesuai. Gunakan file yang diunduh dari menu Hasil Tes.',
            ]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'File XLSX tidak memiliki data peserta.',
            ]);
        }

        $testUuids = collect($rows)
            ->pluck('TEST_UUID')
            ->filter()
            ->unique()
            ->values();

        if ($testUuids->count() !== 1 || ! Str::isUuid((string) $testUuids->first())) {
            throw ValidationException::withMessages([
                'file' => 'File harus berisi tepat satu jenis tes yang valid.',
            ]);
        }

        $test = AdmissionTest::query()->where('uuid', $testUuids->first())->first();

        if (! $test) {
            throw ValidationException::withMessages([
                'file' => 'Tes yang tercantum pada file tidak ditemukan. Download ulang file Hasil Tes terbaru.',
            ]);
        }

        $this->authorize($test, $actor);

        $resultUuids = collect($rows)->pluck('RESULT_UUID')->filter()->values();

        $expectedResultUuids = AdmissionTestResult::query()
            ->where('admission_test_id', $test->id)
            ->where('status', 'scheduled')
            ->whereHas('registration', fn (Builder $query): Builder => $query
                ->where('lifecycle_status', 'active')
                ->where('current_stage', 'tests'))
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('test_bookings')
                    ->whereColumn('test_bookings.registration_id', 'admission_test_results.registration_id')
                    ->whereColumn('test_bookings.admission_test_id', 'admission_test_results.admission_test_id')
                    ->whereNotNull('test_bookings.test_session_id');
            })
            ->pluck('uuid')
            ->sort()
            ->values();

        if ($resultUuids->sort()->values()->all() !== $expectedResultUuids->all()) {
            throw ValidationException::withMessages([
                'file' => 'Daftar peserta pada file sudah tidak sama dengan peserta yang saat ini terjadwal. Download ulang file Hasil Tes terbaru.',
            ]);
        }

        if ($resultUuids->count() !== $resultUuids->unique()->count()) {
            throw ValidationException::withMessages([
                'file' => 'Terdapat RESULT_UUID duplikat di dalam file.',
            ]);
        }

        $models = AdmissionTestResult::query()
            ->with(['registration.configuration', 'admissionTest'])
            ->whereIn('uuid', $resultUuids->all())
            ->get()
            ->keyBy('uuid');

        $updates = [];
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;
            $uuid = trim((string) ($row['RESULT_UUID'] ?? ''));

            if (! Str::isUuid($uuid) || ! $models->has($uuid)) {
                $errors[] = "Baris {$excelRow}: RESULT_UUID tidak valid atau tidak ditemukan.";

                continue;
            }

            /** @var AdmissionTestResult $model */
            $model = $models->get($uuid);
            $registration = $model->registration;

            if ((string) ($row['TEST_UUID'] ?? '') !== $test->uuid
                || $model->admission_test_id !== $test->id) {
                $errors[] = "Baris {$excelRow}: data tes tidak sesuai dengan file export.";

                continue;
            }

            if (! $registration
                || (string) ($row['NO_REGISTRASI'] ?? '') !== (string) $registration->registration_number
                || (string) ($row['NAMA_PESERTA'] ?? '') !== (string) $registration->full_name) {
                $errors[] = "Baris {$excelRow}: identitas peserta telah berubah. Download ulang file hasil tes.";

                continue;
            }

            if ($actor->isTU() && $actor->unit_id !== $registration->unit_id) {
                $errors[] = "Baris {$excelRow}: peserta berada di unit lain.";

                continue;
            }

            try {
                $data = $this->normalizeRow($row, $model, $test, $excelRow);
            } catch (ValidationException $exception) {
                $errors[] = collect($exception->errors())->flatten()->first() ?: "Baris {$excelRow}: data tidak valid.";

                continue;
            }

            if ($data === null) {
                $skipped++;

                continue;
            }

            if ($registration->current_stage !== 'tests') {
                $errors[] = "Baris {$excelRow}: hasil tes {$registration->registration_number} sudah terkunci karena peserta tidak lagi berada di tahap tes.";

                continue;
            }

            if ($model->status !== 'scheduled') {
                $errors[] = "Baris {$excelRow}: peserta {$registration->registration_number} belum memiliki sesi aktif atau hasilnya sudah diproses. Download ulang file terbaru.";

                continue;
            }

            $hasBooking = DB::table('test_bookings')
                ->where('registration_id', $registration->id)
                ->where('admission_test_id', $model->admission_test_id)
                ->whereNotNull('test_session_id')
                ->exists();

            if (! $hasBooking) {
                $errors[] = "Baris {$excelRow}: peserta {$registration->registration_number} belum memilih sesi tes.";

                continue;
            }

            $updates[] = [$model, $data];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => implode("\n", array_slice($errors, 0, 20)),
            ]);
        }

        DB::transaction(function () use ($updates, $actor): void {
            foreach ($updates as [$model, $data]) {
                $this->workflow->recordTestResult($model, $actor, $data);
            }
        });

        return [
            'test' => $test,
            'updated' => count($updates),
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{0:list<string>,1:list<array<string,mixed>>}
     */
    private function readWorkbook(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);

        $headers = [];
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getIndex() !== 0) {
                continue;
            }

            $rowNumber = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $rowNumber++;
                $values = $row->toArray();

                if ($rowNumber === 1) {
                    $headers = array_map(
                        fn ($value): string => trim((string) $value),
                        $values,
                    );

                    continue;
                }

                if (collect($values)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                    continue;
                }

                $values = array_pad($values, count($headers), null);
                $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
            }

            break;
        }

        $reader->close();

        return [$headers, $rows];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{status:string,score:?float,result:string,notes:?string}|null
     */
    private function normalizeRow(array $row, AdmissionTestResult $model, AdmissionTest $test, int $excelRow): ?array
    {
        $status = $this->normalizeStatus($row['STATUS'] ?? null);

        if ($status === null) {
            throw ValidationException::withMessages([
                'file' => "Baris {$excelRow}: STATUS tidak valid.",
            ]);
        }

        $notes = trim((string) ($row['CATATAN'] ?? ''));
        $notes = $notes !== '' ? $notes : null;
        $rawScore = $row['NILAI'] ?? null;
        $inputResult = $this->normalizeResult($row['HASIL'] ?? null);

        if ($status === 'scheduled') {
            if ($model->status !== 'scheduled') {
                throw ValidationException::withMessages([
                    'file' => "Baris {$excelRow}: hasil yang sudah dinilai tidak dapat dikembalikan menjadi TERJADWAL.",
                ]);
            }

            $hasScoreInput = filled($rawScore);
            $hasResultInput = $inputResult !== 'pending';

            if (! $hasScoreInput && ! $hasResultInput) {
                return null;
            }

            $status = 'completed';
        }

        $score = null;
        $result = 'pending';

        if ($status === 'absent') {
            $result = 'fail';
        } elseif ($status === 'exempted') {
            $result = 'pass';
        } elseif ($status === 'completed') {
            if ($test->result_type === 'score') {
                if ($rawScore === null || $rawScore === '' || ! is_numeric($rawScore)) {
                    throw ValidationException::withMessages([
                        'file' => "Baris {$excelRow}: NILAI wajib berupa angka untuk tes bertipe Nilai.",
                    ]);
                }

                $score = (float) $rawScore;

                if ($score < 0) {
                    throw ValidationException::withMessages([
                        'file' => "Baris {$excelRow}: NILAI tidak boleh negatif.",
                    ]);
                }

                if ($test->passing_score !== null) {
                    $result = $score >= (float) $test->passing_score ? 'pass' : 'fail';
                } else {
                    $result = $inputResult ?? 'pending';

                    if ($result === 'pending') {
                        throw ValidationException::withMessages([
                            'file' => "Baris {$excelRow}: HASIL harus LULUS atau TIDAK LULUS karena nilai kelulusan belum dikonfigurasi.",
                        ]);
                    }
                }
            } else {
                $result = $inputResult ?? 'pending';

                if ($result === 'pending') {
                    throw ValidationException::withMessages([
                        'file' => "Baris {$excelRow}: HASIL wajib LULUS atau TIDAK LULUS untuk tes Lulus/Tidak Lulus.",
                    ]);
                }
            }
        }

        $data = [
            'status' => $status,
            'score' => $score,
            'result' => $result,
            'notes' => $notes,
        ];

        $sameScore = $model->score === null
            ? $score === null
            : $score !== null && (float) $model->score === $score;

        if ($model->status === $data['status']
            && $model->result === $data['result']
            && $sameScore
            && ($model->notes ?: null) === $data['notes']) {
            return null;
        }

        return $data;
    }

    private function authorize(AdmissionTest $test, User $actor): void
    {
        abort_unless($actor->can('record_result_admissiontestresult'), 403);
        abort_if($actor->isTU() && $actor->unit_id !== $test->unit_id, 403);
    }

    private function normalizeStatus(mixed $value): ?string
    {
        return match (Str::upper(trim((string) $value))) {
            'TERJADWAL', 'SCHEDULED' => 'scheduled',
            'SELESAI', 'COMPLETED' => 'completed',
            'TIDAK HADIR', 'ABSENT' => 'absent',
            'DIBEBASKAN', 'EXEMPTED' => 'exempted',
            default => null,
        };
    }

    private function normalizeResult(mixed $value): ?string
    {
        return match (Str::upper(trim((string) $value))) {
            '', 'BELUM DINILAI', 'PENDING' => 'pending',
            'LULUS', 'PASS' => 'pass',
            'TIDAK LULUS', 'FAIL' => 'fail',
            default => null,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'SELESAI',
            'absent' => 'TIDAK HADIR',
            'exempted' => 'DIBEBASKAN',
            default => 'TERJADWAL',
        };
    }

    private function resultLabel(string $result): string
    {
        return match ($result) {
            'pass' => 'LULUS',
            'fail' => 'TIDAK LULUS',
            default => 'BELUM DINILAI',
        };
    }
}
