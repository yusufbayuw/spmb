<?php

namespace App\Services;

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

        if ($staff->isTU()) {
            $unit = Unit::query()->where('is_active', true)->find($staff->unit_id);

            if (! $unit) {
                throw ValidationException::withMessages([
                    'template' => 'Akun TU belum memiliki unit aktif.',
                ]);
            }
        }

        $headers = $staff->isTU()
            ? ['va_number', 'bank']
            : ['va_number', 'bank', 'unit'];

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
            $writer->addRow(Row::fromValues([
                'Isi sheet Pool VA dengan nomor VA dan bank. Jangan menambah kolom unit.',
            ]));
        } else {
            $writer->addRow(Row::fromValues([
                'Isi sheet Pool VA dengan nomor VA, bank, dan kode/nama unit.',
            ]));
            $writer->addRow(Row::fromValues([
                'Kolom unit wajib diisi untuk setiap baris karena super admin dapat mengimpor beberapa unit sekaligus.',
            ]));
        }

        $writer->addRow(Row::fromValues([
            'Jangan mengubah nama header pada baris pertama sheet Pool VA.',
        ]));
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
