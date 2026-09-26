<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\UnitConfiguration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

class RegistrationExcelExportService
{
    /**
     * @return array{path:string,filename:string,count:int}
     */
    public function export(Builder $query, User $actor): array
    {
        $query = clone $query;
        $count = (clone $query)->count();

        $configurationIds = (clone $query)
            ->whereNotNull('unit_configuration_id')
            ->distinct()
            ->pluck('unit_configuration_id');

        $configurations = UnitConfiguration::query()
            ->whereIn('id', $configurationIds)
            ->orderBy('version')
            ->get()
            ->keyBy('id');

        $customColumns = $this->customColumns($configurations);

        $directory = storage_path('app/tmp/exports');
        File::ensureDirectoryExists($directory);

        $filename = 'data-pendaftaran-'.now()->format('Ymd-His').'.xlsx';
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

        $this->writeMainSheet($writer, $query, $customColumns, $configurations, $headerStyle);
        $this->writeAcademicScoresSheet($writer, $query, $configurations, $headerStyle);
        $this->writeAchievementsSheet($writer, $query, $headerStyle);
        $this->writeInfoSheet($writer, $actor, $count, $titleStyle, $sectionStyle);

        $writer->close();

        return [
            'path' => $path,
            'filename' => $filename,
            'count' => $count,
        ];
    }

    /**
     * @param Collection<int, UnitConfiguration> $configurations
     * @return array<string, array{label:string,type:string,has_detail:bool}>
     */
    private function customColumns(Collection $configurations): array
    {
        $columns = [];

        foreach ($configurations as $configuration) {
            foreach ($configuration->fields ?? [] as $field) {
                if (! is_array($field)
                    || ! ($field['active'] ?? false)
                    || in_array($field['key'] ?? null, ConfiguredRegistrationForm::BUILTIN_FIELDS, true)) {
                    continue;
                }

                $key = (string) $field['key'];
                $columns[$key] = [
                    'label' => trim((string) ($field['label'] ?? $key)),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'has_detail' => ($field['type'] ?? null) === 'boolean'
                        && ((bool) ($field['boolean_yes_detail_enabled'] ?? false)
                            || (bool) ($field['boolean_no_detail_enabled'] ?? false)),
                ];
            }
        }

        return $columns;
    }

    /**
     * @param array<string, array{label:string,type:string,has_detail:bool}> $customColumns
     * @param Collection<int, UnitConfiguration> $configurations
     */
    private function writeMainSheet(
        Writer $writer,
        Builder $query,
        array $customColumns,
        Collection $configurations,
        Style $headerStyle,
    ): void {
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Data Pendaftaran');
        $sheet->setSheetView((new SheetView())->setFreezeRow(2));

        $headers = [
            'No.',
            'No. Pendaftaran',
            'No. Kartu',
            'Tanggal Daftar',
            'Unit',
            'Tahun Ajaran',
            'Gelombang',
            'Program Studi',
            'Jalur',
            'Tahap Saat Ini',
            'Lifecycle',
            'Status Pendaftaran',
            'Status Validasi Data',
            'Jenis Pendaftar',
            'Hubungan Pendaftar',
            'NIK',
            'Nama Lengkap',
            'Nama Panggilan',
            'Jenis Kelamin',
            'Agama',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Telepon',
            'Email',
            'Alamat Rumah',
            'RT',
            'RW',
            'Provinsi',
            'Kabupaten/Kota',
            'Kecamatan',
            'Desa/Kelurahan',
            'Kode Pos',
            'Sekolah Asal',
            'Tahun Lulus',
            'Nama Ayah',
            'NIK Ayah',
            'Tempat Lahir Ayah',
            'Tanggal Lahir Ayah',
            'Pendidikan Ayah',
            'Pekerjaan Ayah',
            'Instansi / Tempat Kerja Ayah',
            'Telepon Ayah',
            'Email Ayah',
            'Penghasilan Ayah',
            'Nama Ibu',
            'NIK Ibu',
            'Tempat Lahir Ibu',
            'Tanggal Lahir Ibu',
            'Pendidikan Ibu',
            'Pekerjaan Ibu',
            'Instansi / Tempat Kerja Ibu',
            'Telepon Ibu',
            'Email Ibu',
            'Penghasilan Ibu',
            'Catatan Validasi',
        ];

        foreach ($customColumns as $column) {
            $headers[] = $column['label'];

            if ($column['has_detail']) {
                $headers[] = 'Keterangan — '.$column['label'];
            }
        }

        $writer->addRow(Row::fromValues($headers, $headerStyle));
        $this->applyMainWidths($sheet, $headers);

        $rowNumber = 0;
        $dataQuery = clone $query;
        $dataQuery->with([
            'unit',
            'opening.studyProgram',
            'pathway',
            'parentInfo',
            'configuration',
        ]);

        foreach ($dataQuery->lazy(500) as $registration) {
            /** @var Registration $registration */
            $rowNumber++;
            $parent = $registration->parentInfo;
            $answers = is_array($registration->custom_answers) ? $registration->custom_answers : [];
            $configuration = $configurations->get($registration->unit_configuration_id) ?? $registration->configuration;
            $fieldDefinitions = collect($configuration?->fields ?? [])->keyBy('key');

            $values = [
                $rowNumber,
                $registration->registration_number ?: '-',
                $registration->applicant_card_number ?: '-',
                $registration->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: '-',
                $registration->unit?->name ?: '-',
                $registration->opening?->academic_year ?: '-',
                $registration->opening?->wave ?: '-',
                $registration->opening?->studyProgram?->name ?: '-',
                $registration->pathway?->name ?: '-',
                $registration->stageLabel(),
                $registration->lifecycleLabel(),
                $registration->status ?: '-',
                $this->validationStatusLabel($registration->data_validation_status),
                $registration->registrant_type === 'self' ? 'Calon peserta sendiri' : 'Orang tua / wali',
                $this->relationshipLabel($registration->registrant_relationship),
                $registration->nik,
                $registration->full_name,
                $registration->nickname ?: '-',
                $registration->gender === 'L' ? 'Laki-laki' : ($registration->gender === 'P' ? 'Perempuan' : '-'),
                $registration->religion ?: '-',
                $registration->birth_place ?: '-',
                $registration->birth_date?->format('d/m/Y') ?: '-',
                $registration->phone ?: '-',
                $registration->email ?: '-',
                $registration->home_address ?: '-',
                $registration->rt ?: '-',
                $registration->rw ?: '-',
                $registration->province ?: '-',
                $registration->city ?: '-',
                $registration->district ?: '-',
                $registration->village ?: '-',
                $registration->postal_code ?: '-',
                $registration->previous_school ?: '-',
                $registration->graduation_year ?: '-',
                $parent?->father_name ?: '-',
                $parent?->father_nik ?: '-',
                $parent?->father_birth_place ?: '-',
                $parent?->father_birth_date?->format('d/m/Y') ?: '-',
                $parent?->father_education ?: '-',
                $parent?->father_occupation ?: '-',
                $parent?->father_workplace ?: '-',
                $parent?->father_phone ?: '-',
                $parent?->father_email ?: '-',
                $parent?->father_income ?: '-',
                $parent?->mother_name ?: '-',
                $parent?->mother_nik ?: '-',
                $parent?->mother_birth_place ?: '-',
                $parent?->mother_birth_date?->format('d/m/Y') ?: '-',
                $parent?->mother_education ?: '-',
                $parent?->mother_occupation ?: '-',
                $parent?->mother_workplace ?: '-',
                $parent?->mother_phone ?: '-',
                $parent?->mother_email ?: '-',
                $parent?->mother_income ?: '-',
                $registration->data_validation_notes ?: '-',
            ];

            foreach ($customColumns as $key => $column) {
                $definition = $fieldDefinitions->get($key);
                $value = $answers[$key] ?? null;
                $values[] = $this->customAnswerValue($value, $definition ?: ['type' => $column['type']]);

                if ($column['has_detail']) {
                    $values[] = data_get($answers, '_details.'.$key) ?: '-';
                }
            }

            $writer->addRow(Row::fromValues($values));
        }

        $sheet->setAutoFilter(new AutoFilter(
            0,
            1,
            max(0, count($headers) - 1),
            max(1, $rowNumber + 1),
        ));
    }

    /**
     * @param Collection<int, UnitConfiguration> $configurations
     */
    private function writeAcademicScoresSheet(
        Writer $writer,
        Builder $query,
        Collection $configurations,
        Style $headerStyle,
    ): void {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Nilai Akademik');
        $sheet->setSheetView((new SheetView())->setFreezeRow(2));

        $headers = ['No. Pendaftaran', 'NIK', 'Nama Lengkap', 'Kelas / Tingkat', 'Mata Pelajaran', 'Komponen Nilai', 'Nilai'];
        $writer->addRow(Row::fromValues($headers, $headerStyle));
        $this->setWidths($sheet, [20, 20, 30, 20, 24, 28, 12]);

        $rowCount = 0;
        $dataQuery = clone $query;
        $dataQuery->with(['academicScores', 'configuration']);

        foreach ($dataQuery->lazy(500) as $registration) {
            $configuration = $configurations->get($registration->unit_configuration_id) ?? $registration->configuration;
            $settings = is_array($configuration?->academic_score_settings)
                ? $configuration->academic_score_settings
                : [];

            $grades = collect($settings['grades'] ?? [])->pluck('label', 'key');
            $subjects = collect($settings['subjects'] ?? [])->pluck('label', 'key');
            $assessments = collect($settings['assessments'] ?? [])->pluck('label', 'key');

            foreach ($registration->academicScores as $score) {
                $rowCount++;
                $writer->addRow(Row::fromValues([
                    $registration->registration_number ?: '-',
                    $registration->nik,
                    $registration->full_name,
                    $grades[$score->grade_key] ?? $score->grade_key,
                    $subjects[$score->subject_key] ?? $score->subject_key,
                    $assessments[$score->assessment_key] ?? $score->assessment_key,
                    (float) $score->score,
                ]));
            }
        }

        $sheet->setAutoFilter(new AutoFilter(0, 1, 6, max(1, $rowCount + 1)));
    }

    private function writeAchievementsSheet(Writer $writer, Builder $query, Style $headerStyle): void
    {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Prestasi');
        $sheet->setSheetView((new SheetView())->setFreezeRow(2));

        $headers = ['No. Pendaftaran', 'NIK', 'Nama Lengkap', 'Nama Prestasi', 'Tingkat', 'Tahun', 'Penyelenggara', 'Keterangan'];
        $writer->addRow(Row::fromValues($headers, $headerStyle));
        $this->setWidths($sheet, [20, 20, 30, 32, 18, 12, 28, 40]);

        $rowCount = 0;
        $dataQuery = clone $query;
        $dataQuery->with('achievements');

        foreach ($dataQuery->lazy(500) as $registration) {
            foreach ($registration->achievements as $achievement) {
                $rowCount++;
                $writer->addRow(Row::fromValues([
                    $registration->registration_number ?: '-',
                    $registration->nik,
                    $registration->full_name,
                    $achievement->title ?: '-',
                    $achievement->level ?: '-',
                    $achievement->year ?: '-',
                    $achievement->organizer ?: '-',
                    $achievement->description ?: '-',
                ]));
            }
        }

        $sheet->setAutoFilter(new AutoFilter(0, 1, 7, max(1, $rowCount + 1)));
    }

    private function writeInfoSheet(
        Writer $writer,
        User $actor,
        int $count,
        Style $titleStyle,
        Style $sectionStyle,
    ): void {
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('Info Export');
        $this->setWidths($sheet, [28, 60]);

        $writer->addRow(Row::fromValues(['EXPORT DATA PENDAFTARAN SPMB'], $titleStyle));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Informasi', 'Nilai'], $sectionStyle));
        $writer->addRow(Row::fromValues(['Dibuat pada', now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s')]));
        $writer->addRow(Row::fromValues(['Dibuat oleh', $actor->name ?: $actor->email]));
        $writer->addRow(Row::fromValues(['Jumlah pendaftaran', $count]));
        $writer->addRow(Row::fromValues(['Cakupan data', 'Mengikuti filter, pencarian, dan tab yang aktif pada tabel Pendaftaran saat export dijalankan.']));
        $writer->addRow(Row::fromValues(['Catatan', 'Nilai akademik dan prestasi ditempatkan pada sheet terpisah agar satu baris pada sheet utama tetap mewakili satu pendaftaran.']));
    }

    private function customAnswerValue(mixed $value, array $definition): string|int|float
    {
        if (blank($value) && $value !== 0 && $value !== '0' && $value !== false) {
            return '-';
        }

        return match ($definition['type'] ?? null) {
            'boolean' => in_array($value, [true, 1, '1'], true) ? 'Ya' : 'Tidak',
            'multiselect' => is_array($value) ? implode(', ', $value) : (string) $value,
            'file' => 'Sudah diunggah',
            default => is_array($value) ? implode(', ', $value) : (string) $value,
        };
    }

    private function validationStatusLabel(?string $status): string
    {
        return match ($status) {
            'valid' => 'Valid',
            'revision' => 'Perlu Revisi',
            'pending' => 'Menunggu Validasi',
            default => $status ?: '-',
        };
    }

    private function relationshipLabel(?string $relationship): string
    {
        return match ($relationship) {
            'father' => 'Ayah',
            'mother' => 'Ibu',
            'guardian' => 'Wali',
            'self' => 'Diri Sendiri',
            'other' => 'Lainnya',
            default => $relationship ?: '-',
        };
    }

    private function applyMainWidths(object $sheet, array $headers): void
    {
        foreach ($headers as $index => $header) {
            $width = match (true) {
                in_array($header, ['No.'], true) => 7,
                str_contains($header, 'Alamat') || str_contains($header, 'Catatan') || str_contains($header, 'Keterangan') => 36,
                str_contains($header, 'Nama Lengkap') || str_contains($header, 'Instansi') || str_contains($header, 'Program Studi') => 28,
                str_contains($header, 'Email') => 28,
                str_contains($header, 'Tanggal') || str_contains($header, 'No.') || $header === 'NIK' => 20,
                default => 18,
            };

            $sheet->setColumnWidth($width, $index + 1);
        }
    }

    private function setWidths(object $sheet, array $widths): void
    {
        foreach ($widths as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }
    }
}
