<?php

namespace App\Services;

use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VirtualAccountTemplateService
{
    public function generate(User $staff): array
    {
        $unit = null;
        $programs = collect();

        if ($staff->isTU()) {
            $unit = Unit::query()->where('is_active', true)->find($staff->unit_id);

            if (! $unit) {
                throw ValidationException::withMessages([
                    'template' => 'Akun unit belum memiliki unit aktif.',
                ]);
            }

            $programs = $unit->studyPrograms()
                ->where('is_active', true)
                ->get();
        }

        $hasPrograms = $staff->isTU()
            ? $programs->isNotEmpty()
            : StudyProgram::query()->where('is_active', true)->exists();

        $headers = match (true) {
            $staff->isTU() && $hasPrograms => ['va_number', 'bank', 'prodi'],
            $staff->isTU() => ['va_number', 'bank'],
            default => ['va_number', 'bank', 'unit', 'prodi'],
        };

        $downloadName = $staff->isTU()
            ? 'template-pool-va-'.strtolower($unit->code).'.xlsx'
            : 'template-pool-va-super-admin.xlsx';

        $storedPath = 'exports/virtual-accounts/'.Str::uuid().'.xlsx';
        Storage::disk('local')->makeDirectory('exports/virtual-accounts');
        $absolutePath = Storage::disk('local')->path($storedPath);

        $writer = new Writer();
        $writer->openToFile($absolutePath);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Pool VA');
        $writer->addRow(Row::fromValues($headers));

        $instructionSheet = $writer->addNewSheetAndMakeItCurrent();
        $instructionSheet->setName('Petunjuk');
        $writer->addRow(Row::fromValues(['PETUNJUK TEMPLATE POOL VIRTUAL ACCOUNT']));

        if ($staff->isTU()) {
            $writer->addRow(Row::fromValues([
                "Unit otomatis: {$unit->code} - {$unit->name}",
            ]));

            if ($hasPrograms) {
                $writer->addRow(Row::fromValues([
                    'Isi sheet Pool VA dengan nomor VA, bank, dan kode prodi. Kosongkan prodi untuk membuat VA umum sebagai fallback semua prodi pada unit ini.',
                ]));
            } else {
                $writer->addRow(Row::fromValues([
                    'Isi sheet Pool VA dengan nomor VA dan bank. Unit ini tidak memiliki program studi aktif, sehingga kolom prodi tidak diperlukan.',
                ]));
            }
        } else {
            $writer->addRow(Row::fromValues([
                'Isi sheet Pool VA dengan nomor VA, bank, kode/nama unit, dan kode prodi bila VA khusus untuk prodi tertentu.',
            ]));
            $writer->addRow(Row::fromValues([
                'Kolom unit wajib diisi untuk setiap baris. Kolom prodi boleh kosong; nilai kosong berarti VA umum pada unit tersebut.',
            ]));
        }

        $writer->addRow(Row::fromValues([
            'Kolom prodi menggunakan kode program studi, bukan nama. Jangan mengubah nama header pada baris pertama sheet Pool VA.',
        ]));

        if ($hasPrograms) {
            $referenceSheet = $writer->addNewSheetAndMakeItCurrent();
            $referenceSheet->setName('Referensi Prodi');

            if ($staff->isTU()) {
                $writer->addRow(Row::fromValues(['Kode Prodi', 'Nama Prodi']));
                foreach ($programs as $program) {
                    $writer->addRow(Row::fromValues([$program->code, $program->name]));
                }
            } else {
                $writer->addRow(Row::fromValues(['Unit', 'Kode Prodi', 'Nama Prodi']));
                $allPrograms = StudyProgram::query()
                    ->with('unit')
                    ->where('is_active', true)
                    ->orderBy('unit_id')
                    ->orderBy('sort_order')
                    ->orderBy('degree_level')
                    ->orderBy('name')
                    ->get();

                foreach ($allPrograms as $program) {
                    $writer->addRow(Row::fromValues([
                        $program->unit?->code,
                        $program->code,
                        $program->name,
                    ]));
                }
            }
        }

        $writer->close();

        return [
            'path' => $absolutePath,
            'stored_path' => $storedPath,
            'filename' => $downloadName,
            'headers' => $headers,
        ];
    }

    public function download(User $staff): BinaryFileResponse
    {
        $template = $this->generate($staff);

        return response()
            ->download(
                $template['path'],
                $template['filename'],
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }
}
