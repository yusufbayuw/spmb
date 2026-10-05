<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContinuationCandidateTemplateService
{
    /**
     * @return array{path:string,stored_path:string,filename:string}
     */
    public function generate(User $actor): array
    {
        $unit = $actor->isAdminUnit() && $actor->unit_id
            ? Unit::query()->find($actor->unit_id)
            : null;

        $storedPath = 'exports/continuation/'.Str::uuid().'.xlsx';
        Storage::disk('local')->makeDirectory('exports/continuation');
        $absolutePath = Storage::disk('local')->path($storedPath);

        $writer = new Writer();
        $writer->openToFile($absolutePath);

        $headerStyle = (new Style())
            ->setFontBold()
            ->setShouldWrapText();

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Data Terusan');
        $sheet->setSheetView((new SheetView())->setFreezeRow(3));

        $topHeaders = [
            'Nama',
            'NIPD',
            'JK',
            'NISN',
            'Tempat Lahir',
            'Tanggal Lahir',
            'NIK',
            'Agama',
            'Alamat',
            'RT',
            'RW',
            'Dusun',
            'Kelurahan',
            'Kecamatan',
            'Kode Pos',
            'Jenis Tinggal',
            'Alat Transportasi',
            'Telepon',
            'HP',
            'E-Mail',
            'SKHUN',
            'Penerima KPS',
            'No. KPS',
            'Sekolah Asal',
            'Data Ayah',
            '',
            '',
            '',
            '',
            '',
            'Data Ibu',
            '',
            '',
            '',
            '',
            '',
            'Data Wali',
            '',
            '',
            '',
            '',
            '',
        ];

        $subHeaders = [
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            'Nama',
            'Tahun Lahir',
            'Jenjang Pendidikan',
            'Pekerjaan',
            'Penghasilan',
            'NIK',
            'Nama',
            'Tahun Lahir',
            'Jenjang Pendidikan',
            'Pekerjaan',
            'Penghasilan',
            'NIK',
            'Nama',
            'Tahun Lahir',
            'Jenjang Pendidikan',
            'Pekerjaan',
            'Penghasilan',
            'NIK',
        ];

        $writer->addRow(Row::fromValues($topHeaders, $headerStyle));
        $writer->addRow(Row::fromValues($subHeaders, $headerStyle));

        foreach (range(1, count($topHeaders)) as $column) {
            $sheet->setColumnWidth(18, $column);
        }

        $instructionSheet = $writer->addNewSheetAndMakeItCurrent();
        $instructionSheet->setName('Petunjuk');
        $writer->addRow(Row::fromValues(['PETUNJUK IMPORT DATA TERUSAN'], $headerStyle));
        $writer->addRow(Row::fromValues([
            'Isi data hanya pada sheet "Data Terusan". Jangan mengubah nama, urutan, atau dua baris header.',
        ]));
        $writer->addRow(Row::fromValues([
            'Sekolah Asal wajib diisi. Matching otomatis ke formulir pendaftaran menggunakan NIK 16 digit + Tanggal Lahir.',
        ]));
        $writer->addRow(Row::fromValues([
            'Format Tanggal Lahir yang disarankan: DD/MM/YYYY, contoh 17/08/2014.',
        ]));
        $writer->addRow(Row::fromValues([
            'NIK harus disimpan sebagai teks/angka utuh 16 digit. Hindari format scientific notation dari Excel.',
        ]));
        $writer->addRow(Row::fromValues([
            'Kolom yang belum memiliki field langsung di formulir tetap disimpan sebagai data sumber dan tidak dibuang.',
        ]));

        if ($unit) {
            $writer->addRow(Row::fromValues([
                'Template ini digunakan untuk unit: '.$unit->code.' - '.$unit->name.'. Unit tujuan dipilih saat proses import.',
            ]));
        }

        $exampleSheet = $writer->addNewSheetAndMakeItCurrent();
        $exampleSheet->setName('Contoh');
        $writer->addRow(Row::fromValues($topHeaders, $headerStyle));
        $writer->addRow(Row::fromValues($subHeaders, $headerStyle));
        $writer->addRow(Row::fromValues([
            'Budi Santoso',
            '12345',
            'L',
            '0012345678',
            'Bandung',
            '17/08/2014',
            '3273011708140001',
            'Islam',
            'Jl. Contoh No. 1',
            '001',
            '005',
            '',
            'Dago',
            'Coblong',
            '40135',
            'Bersama Orang Tua',
            'Jalan Kaki',
            '0221234567',
            '081234567890',
            'budi@example.test',
            '',
            'Tidak',
            '',
            'SD Contoh',
            'Ayah Budi',
            '1980',
            'S1',
            'Wiraswasta',
            '5000000',
            '3273010101800001',
            'Ibu Budi',
            '1982',
            'SMA',
            'Guru',
            '4000000',
            '3273010101820002',
            '',
            '',
            '',
            '',
            '',
            '',
        ]));

        $writer->close();

        $filename = $unit
            ? 'template-import-terusan-'.Str::slug($unit->code).'.xlsx'
            : 'template-import-terusan.xlsx';

        return [
            'path' => $absolutePath,
            'stored_path' => $storedPath,
            'filename' => $filename,
        ];
    }

    public function download(User $actor): BinaryFileResponse
    {
        $template = $this->generate($actor);

        return response()
            ->download(
                $template['path'],
                $template['filename'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ],
            )
            ->deleteFileAfterSend(true);
    }
}
