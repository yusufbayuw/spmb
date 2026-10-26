<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\RegistrationConsentService;
use App\Services\RegistrationExcelExportService;
use App\Services\UnitConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

class RegistrationExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_export_creates_readable_formatted_workbook_with_dynamic_data(): void
    {
        $unit = Unit::create([
            'name' => 'SMP Taruna Test',
            'code' => 'SMP',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        $actor = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $configurationData = app(UnitConfigurationService::class)->defaults($unit);
        $configurationData['fields'] = [[
            'key' => 'has_condition',
            'label' => 'Apakah memiliki kondisi khusus?',
            'type' => 'boolean',
            'active' => true,
            'required' => true,
            'group' => 'Informasi Tambahan',
            'group_key' => 'group_additional',
            'help' => null,
            'options' => [],
            'boolean_yes_detail_enabled' => true,
            'boolean_yes_detail_label' => 'Jelaskan kondisi khusus',
            'boolean_yes_detail_required' => false,
            'boolean_no_detail_enabled' => false,
            'boolean_no_detail_label' => 'Keterangan',
            'boolean_no_detail_required' => false,
        ]];
        $configurationData['academic_scores_enabled'] = true;
        $configurationData['academic_score_settings'] = [
            'required' => false,
            'min_score' => 0,
            'max_score' => 100,
            'pathway_uuids' => [],
            'grades' => [['key' => 'vii', 'label' => 'Kelas VII']],
            'subjects' => [['key' => 'matematika', 'label' => 'Matematika']],
            'assessments' => [['key' => 'rapor_s1', 'label' => 'Rapor Semester 1', 'grade_keys' => ['vii']]],
        ];

        $configuration = UnitConfiguration::create(array_merge($configurationData, [
            'unit_id' => $unit->id,
            'version' => 1,
            'status' => 'published',
            'published_at' => now(),
        ]));

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);

        $pathway = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);

        $applicant = User::factory()->create(['is_active' => true]);

        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'unit_configuration_id' => $configuration->id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'registration_number' => 'SMP-001',
            'applicant_card_number' => 'KARTU-SMP-001',
            'nik' => '3273010101010001',
            'full_name' => 'Peserta Export',
            'nisn' => '0012345678',
            'nickname' => 'Peserta',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2020-01-02',
            'religion' => 'Islam',
            'home_address' => 'Jalan Contoh 1',
            'rt' => '001',
            'rw' => '007',
            'province' => 'Jawa Barat',
            'city' => 'Kota Bandung',
            'district' => 'Coblong',
            'village' => 'Dago',
            'phone' => '08123456789',
            'email' => 'peserta@example.test',
            'previous_school' => 'SD Contoh',
            'graduation_year' => 2026,
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
            'custom_answers' => [
                'has_condition' => '1',
                '_details' => ['has_condition' => 'Memerlukan pendampingan khusus.'],
            ],
        ]);

        $registration->parentInfo()->create([
            'father_name' => 'Ayah Peserta',
            'father_nik' => '3273010101010002',
            'father_occupation' => 'Guru',
            'father_workplace' => 'Sekolah Contoh',
            'mother_name' => 'Ibu Peserta',
            'mother_nik' => '3273010101010003',
            'mother_occupation' => 'Dokter',
            'mother_workplace' => 'Rumah Sakit Contoh',
        ]);

        $registration->academicScores()->create([
            'grade_key' => 'vii',
            'subject_key' => 'matematika',
            'assessment_key' => 'rapor_s1',
            'score' => 88.5,
        ]);

        $registration->achievements()->create([
            'title' => 'Juara Olimpiade',
            'level' => 'Kota',
            'year' => 2025,
            'organizer' => 'Panitia Test',
            'description' => 'Juara 1',
        ]);

        $consentService = app(RegistrationConsentService::class);
        $consent = $consentService->accept($applicant, $opening, $configuration, '127.0.0.1', 'PHPUnit');
        $consentService->attachToRegistration($consent->id, $registration);

        $result = app(RegistrationExcelExportService::class)->export(
            Registration::query()->whereKey($registration->id),
            $actor,
        );

        $this->assertSame(1, $result['count']);
        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.xlsx', $result['filename']);

        $sheets = $this->readWorkbook($result['path']);

        $this->assertSame(
            ['Data Pendaftaran', 'Nilai Akademik', 'Rekap Nilai', 'Prestasi', 'Info Export'],
            array_keys($sheets),
        );

        $mainHeaders = $sheets['Data Pendaftaran'][0];
        $mainRow = $sheets['Data Pendaftaran'][1];

        $this->assertContains('No. Pendaftaran', $mainHeaders);
        $this->assertContains('Apakah memiliki kondisi khusus?', $mainHeaders);
        $this->assertContains('Keterangan — Apakah memiliki kondisi khusus?', $mainHeaders);
        $this->assertContains('Persetujuan Data Pribadi', $mainHeaders);
        $this->assertContains('Tanggal Persetujuan', $mainHeaders);
        $this->assertContains('SMP-001', $mainRow);
        $nisnColumn = array_search('NISN', $mainHeaders, true);
        $this->assertIsInt($nisnColumn);
        $this->assertSame('0012345678', $mainRow[$nisnColumn]);
        $this->assertContains('Peserta Export', $mainRow);
        $this->assertContains('Ya', $mainRow);
        $this->assertContains('Memerlukan pendampingan khusus.', $mainRow);
        $consentStatusColumn = array_search('Persetujuan Data Pribadi', $mainHeaders, true);
        $consentDateColumn = array_search('Tanggal Persetujuan', $mainHeaders, true);
        $this->assertIsInt($consentStatusColumn);
        $this->assertIsInt($consentDateColumn);
        $this->assertSame('Ya', $mainRow[$consentStatusColumn]);
        $this->assertNotSame('-', $mainRow[$consentDateColumn]);

        $this->assertContains('Kelas VII', $sheets['Nilai Akademik'][1]);
        $this->assertContains('Matematika', $sheets['Nilai Akademik'][1]);
        $this->assertContains('Rapor Semester 1', $sheets['Nilai Akademik'][1]);
        $this->assertContains(88.5, $sheets['Nilai Akademik'][1]);

        $this->assertContains('Matematika', $sheets['Rekap Nilai'][0]);
        $this->assertContains('Kelas VII', $sheets['Rekap Nilai'][1]);
        $this->assertContains('Rapor Semester 1', $sheets['Rekap Nilai'][2]);
        $this->assertContains(88.5, $sheets['Rekap Nilai'][3]);

        $this->assertContains('Juara Olimpiade', $sheets['Prestasi'][1]);
        $this->assertContains('Panitia Test', $sheets['Prestasi'][1]);

        $countRow = collect($sheets['Info Export'])
            ->first(fn (array $row): bool => ($row[0] ?? null) === 'Jumlah pendaftaran');

        $this->assertIsArray($countRow);
        $this->assertContains(1, $countRow);

        @unlink($result['path']);
    }

    public function test_score_recap_preserves_historical_labels_and_keeps_one_row_per_registration(): void
    {
        $unit = Unit::create([
            'name' => 'SMP Historis',
            'code' => 'HIST',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $actor = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $base = app(UnitConfigurationService::class)->defaults($unit);
        $base['academic_scores_enabled'] = true;
        $base['academic_score_settings'] = [
            'required' => false,
            'min_score' => 0,
            'max_score' => 100,
            'pathway_uuids' => [],
            'grades' => [
                ['key' => 'vii', 'label' => 'Kelas VII'],
                ['key' => 'viii', 'label' => 'Kelas VIII'],
            ],
            'subjects' => [['key' => 'mat', 'label' => 'Matematika']],
            'assessments' => [
                ['key' => 'rapor_s1', 'label' => 'Rapor S1', 'grade_keys' => ['vii', 'viii']],
                ['key' => 'rapor_s2', 'label' => 'Rapor S2', 'grade_keys' => ['vii', 'viii']],
            ],
        ];
        $old = UnitConfiguration::create(array_merge($base, [
            'unit_id' => $unit->id,
            'version' => 1,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]));

        // The same "mat" key has a new meaning in the newer configuration.
        $newData = $base;
        $newData['academic_score_settings']['subjects'] = [
            ['key' => 'mat', 'label' => 'Matematika Lanjutan'],
            ['key' => 'ipa', 'label' => 'IPA'],
        ];
        $new = UnitConfiguration::create(array_merge($newData, [
            'unit_id' => $unit->id,
            'version' => 2,
            'status' => 'published',
            'published_at' => now(),
        ]));

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);
        $pathway = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);

        $registrations = [];
        foreach ([
            ['SMP-OLD', '3273010101010001', 'Peserta Lama', $old],
            ['SMP-NEW', '3273010101010002', 'Peserta Baru', $new],
            ['SMP-EMPTY', '3273010101010003', 'Belum Mengisi', $new],
        ] as [$number, $nik, $name, $configuration]) {
            $registrations[] = Registration::create([
                'user_id' => User::factory()->create()->id,
                'unit_id' => $unit->id,
                'unit_configuration_id' => $configuration->id,
                'registration_opening_id' => $opening->id,
                'registration_pathway_id' => $pathway->id,
                'registrant_type' => 'self',
                'registrant_relationship' => 'self',
                'registration_number' => $number,
                'nik' => $nik,
                'full_name' => $name,
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2013-01-01',
                'home_address' => 'Alamat Uji',
                'status' => 'submitted',
                'current_stage' => 'data_validation',
                'data_validation_status' => 'pending',
            ]);
        }

        $registrations[0]->academicScores()->create([
            'grade_key' => 'vii',
            'subject_key' => 'mat',
            'assessment_key' => 'rapor_s1',
            'score' => 80.5,
        ]);
        $registrations[1]->academicScores()->create([
            'grade_key' => 'vii',
            'subject_key' => 'mat',
            'assessment_key' => 'rapor_s1',
            'score' => 93,
        ]);

        $result = app(RegistrationExcelExportService::class)->export(
            Registration::query()->whereIn('id', array_map(
                static fn (Registration $registration): int => $registration->id,
                $registrations,
            ))->orderBy('id'),
            $actor,
        );

        try {
            $sheets = $this->readWorkbook($result['path']);
            $rows = $sheets['Rekap Nilai'];

            $this->assertCount(6, $rows);
            $this->assertSame(['SMP-OLD', 'SMP-NEW', 'SMP-EMPTY'], [
                $rows[3][1],
                $rows[4][1],
                $rows[5][1],
            ]);

            $oldColumn = array_search('Matematika', $rows[0], true);
            $newColumn = array_search('Matematika Lanjutan', $rows[0], true);
            $this->assertIsInt($oldColumn);
            $this->assertIsInt($newColumn);
            $this->assertNotSame($oldColumn, $newColumn);
            $this->assertSame('Kelas VII', $rows[1][$oldColumn]);
            $this->assertSame('Kelas VIII', $rows[1][$oldColumn + 2]);
            $this->assertSame('Rapor S1', $rows[2][$oldColumn]);
            $this->assertSame('Rapor S2', $rows[2][$oldColumn + 1]);
            $this->assertSame(80.5, (float) $rows[3][$oldColumn]);
            $this->assertTrue(blank($rows[3][$newColumn] ?? null));
            $this->assertSame(93.0, (float) $rows[4][$newColumn]);
            $this->assertTrue(blank($rows[4][$oldColumn] ?? null));
            $this->assertTrue(blank($rows[5][$oldColumn] ?? null));
            $this->assertTrue(blank($rows[5][$newColumn] ?? null));

            // Multilevel headers are real Excel merges, not merely repeated labels.
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($result['path']) === true);
            try {
                $sheetXml = $zip->getFromName('xl/worksheets/sheet3.xml');
                $this->assertIsString($sheetXml);
                $this->assertStringContainsString('ref="A1:A3"', $sheetXml);
                $this->assertStringContainsString('ref="F1:I1"', $sheetXml);
                $this->assertStringContainsString('ref="F2:G2"', $sheetXml);
                $this->assertStringContainsString('ref="H2:I2"', $sheetXml);
            } finally {
                $zip->close();
            }

            // The original detailed sheet is still one row per saved score.
            $this->assertCount(3, $sheets['Nilai Akademik']);
        } finally {
            @unlink($result['path']);
        }
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function readWorkbook(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);

        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();

        return $sheets;
    }
}
