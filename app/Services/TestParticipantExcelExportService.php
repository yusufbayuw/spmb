<?php

namespace App\Services;

use App\Models\TestSession;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

class TestParticipantExcelExportService
{
    /**
     * @return array{path:string,filename:string,count:int}
     */
    public function export(TestSession $session, User $actor): array
    {
        $session->loadMissing('admissionTest.unit');

        abort_unless(
            $actor->isAdmin()
            || ($actor->isAdminUnit() && (int) $actor->unit_id === (int) $session->admissionTest->unit_id),
            403,
        );

        $query = $session->bookings()
            ->whereNotNull('test_session_id')
            ->with([
                'registration.opening.studyProgram',
                'registration.unit',
            ])
            ->orderBy('id');

        $count = (clone $query)->count();

        $directory = storage_path('app/tmp/exports');
        File::ensureDirectoryExists($directory);

        $testSlug = Str::slug($session->admissionTest->name) ?: 'tes';
        $filename = 'peserta-'.$testSlug.'-'.$session->starts_at->format('Ymd-Hi').'.xlsx';
        $path = $directory.'/'.Str::uuid().'.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);

        $headerStyle = (new Style())
            ->setFontBold()
            ->setFontColor('FFFFFF')
            ->setBackgroundColor('1F4E78')
            ->setShouldWrapText();

        $sectionStyle = (new Style())
            ->setFontBold()
            ->setFontColor('1F1F1F')
            ->setBackgroundColor('D9EAF7')
            ->setShouldWrapText();

        $titleStyle = (new Style())
            ->setFontBold()
            ->setFontSize(14)
            ->setFontColor('1F4E78');

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Peserta Tes');
        $sheet->setSheetView((new SheetView())->setFreezeRow(2));

        $headers = [
            'No.',
            'No. Pendaftaran',
            'Nama Peserta',
            'NIK',
            'Jenis Kelamin',
            'Program Studi',
            'Tes',
            'Mulai',
            'Selesai',
            'Lokasi',
            'No. HP',
            'Email',
            'Sekolah Asal',
            'Jadwal Dikonfirmasi',
            'Revisi Booking',
        ];

        $writer->addRow(Row::fromValues($headers, $headerStyle));

        $widths = [7, 20, 30, 20, 16, 28, 28, 20, 20, 28, 18, 30, 28, 22, 16];

        foreach ($widths as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }

        $rowNumber = 0;

        foreach ($query->lazy(500) as $booking) {
            $registration = $booking->registration;

            if (! $registration) {
                continue;
            }

            $rowNumber++;

            $writer->addRow(Row::fromValues([
                $rowNumber,
                $registration->registration_number ?: '-',
                $registration->full_name,
                $registration->nik ?: '-',
                $registration->gender === 'L' ? 'Laki-laki' : ($registration->gender === 'P' ? 'Perempuan' : '-'),
                $registration->opening?->studyProgram?->name ?: '-',
                $session->admissionTest->name,
                $session->starts_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                $session->ends_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                $session->location ?: '-',
                $registration->phone ?: '-',
                $registration->email ?: '-',
                $registration->previous_school ?: '-',
                $registration->test_schedule_confirmed_at ? 'Ya' : 'Belum',
                (int) $booking->revision,
            ]));
        }

        $sheet->setAutoFilter(new AutoFilter(
            0,
            1,
            count($headers) - 1,
            max(1, $rowNumber + 1),
        ));

        $infoSheet = $writer->addNewSheetAndMakeItCurrent();
        $infoSheet->setName('Info Sesi');
        $infoSheet->setColumnWidth(30, 1);
        $infoSheet->setColumnWidth(60, 2);

        $writer->addRow(Row::fromValues(['EXPORT PESERTA TES'], $titleStyle));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Informasi', 'Nilai'], $sectionStyle));
        $writer->addRow(Row::fromValues(['Unit', $session->admissionTest->unit?->name ?: '-']));
        $writer->addRow(Row::fromValues(['Tes', $session->admissionTest->name]));
        $writer->addRow(Row::fromValues(['Mulai', $session->starts_at->timezone(config('app.timezone'))->format('d/m/Y H:i')]));
        $writer->addRow(Row::fromValues(['Selesai', $session->ends_at->timezone(config('app.timezone'))->format('d/m/Y H:i')]));
        $writer->addRow(Row::fromValues(['Lokasi', $session->location ?: '-']));
        $writer->addRow(Row::fromValues(['Kuota', $session->capacity]));
        $writer->addRow(Row::fromValues(['Jumlah peserta', $count]));
        $writer->addRow(Row::fromValues(['Dibuat pada', now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s')]));
        $writer->addRow(Row::fromValues(['Dibuat oleh', $actor->name ?: $actor->email]));

        $writer->close();

        return [
            'path' => $path,
            'filename' => $filename,
            'count' => $count,
        ];
    }
}
