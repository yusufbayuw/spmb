<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\RegistrationExcelExportService;
use App\Services\UnitConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

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

        $result = app(RegistrationExcelExportService::class)->export(
            Registration::query()->whereKey($registration->id),
            $actor,
        );

        $this->assertSame(1, $result['count']);
        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.xlsx', $result['filename']);

        $sheets = $this->readWorkbook($result['path']);

        $this->assertSame(
            ['Data Pendaftaran', 'Nilai Akademik', 'Prestasi', 'Info Export'],
            array_keys($sheets),
        );

        $mainHeaders = $sheets['Data Pendaftaran'][0];
        $mainRow = $sheets['Data Pendaftaran'][1];

        $this->assertContains('No. Pendaftaran', $mainHeaders);
        $this->assertContains('Apakah memiliki kondisi khusus?', $mainHeaders);
        $this->assertContains('Keterangan — Apakah memiliki kondisi khusus?', $mainHeaders);
        $this->assertContains('SMP-001', $mainRow);
        $this->assertContains('Peserta Export', $mainRow);
        $this->assertContains('Ya', $mainRow);
        $this->assertContains('Memerlukan pendampingan khusus.', $mainRow);

        $this->assertContains('Kelas VII', $sheets['Nilai Akademik'][1]);
        $this->assertContains('Matematika', $sheets['Nilai Akademik'][1]);
        $this->assertContains('Rapor Semester 1', $sheets['Nilai Akademik'][1]);
        $this->assertContains(88.5, $sheets['Nilai Akademik'][1]);

        $this->assertContains('Juara Olimpiade', $sheets['Prestasi'][1]);
        $this->assertContains('Panitia Test', $sheets['Prestasi'][1]);

        $this->assertContains('Jumlah pendaftaran', $sheets['Info Export'][5] ?? []);
        $this->assertContains(1, $sheets['Info Export'][5] ?? []);

        @unlink($result['path']);
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
