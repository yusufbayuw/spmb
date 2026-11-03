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
use ZipArchive;

class RegistrationSchoolRecapSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_recap_groups_only_exported_applicants_and_preserves_original_school_data(): void
    {
        $unit = Unit::create([
            'name' => 'Unit Uji Rekap',
            'code' => 'URK',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $actor = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $configuration = UnitConfiguration::create(array_merge(
            app(UnitConfigurationService::class)->defaults($unit),
            [
                'unit_id' => $unit->id,
                'version' => 1,
                'status' => 'published',
                'published_at' => now(),
            ],
        ));
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

        config()->set('registration_school_recap.aliases', [
            'MELATI JAYA' => 'SMP MELATI JAYA',
        ]);

        $includedIds = [];
        foreach ([
            ' SMP Melati   Jaya ',
            'SMP MELATI JAYA',
            'Melati Jaya',
            'SMPN 2 BANDUNG',
            'smp negeri 2 bandung',
            null,
            'SMP Darul Hikam',
            'SMP Darul Hikam Integrated School',
            'Sekolah di luar filter',
        ] as $index => $school) {
            $registration = Registration::create([
                'user_id' => User::factory()->create()->id,
                'unit_id' => $unit->id,
                'unit_configuration_id' => $configuration->id,
                'registration_opening_id' => $opening->id,
                'registration_pathway_id' => $pathway->id,
                'registrant_type' => 'self',
                'registrant_relationship' => 'self',
                'registration_number' => 'URK-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'nik' => '327301010101'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'full_name' => 'Pendaftar '.$index,
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2013-01-01',
                'home_address' => 'Alamat Uji',
                'previous_school' => $school,
                'status' => 'submitted',
                'current_stage' => 'data_validation',
                'data_validation_status' => 'pending',
            ]);

            // A record present in the database but excluded by the current export
            // selection must not leak into the school recap.
            if ($index < 8) {
                $includedIds[] = $registration->id;
            }
        }

        $result = app(RegistrationExcelExportService::class)->export(
            Registration::query()->whereIn('id', $includedIds)->orderBy('id'),
            $actor,
        );

        try {
            $this->assertSame(8, $result['count']);
            $sheets = $this->readWorkbook($result['path']);
            $this->assertSame(
                ['Data Pendaftaran', 'Nilai Akademik', 'Rekap Nilai', 'Prestasi', 'rekap_sekolah', 'Info Export'],
                array_keys($sheets),
            );

            $recap = $sheets['rekap_sekolah'];
            $this->assertStringContainsString('Total Pendaftar: 8', $recap[1][0]);
            $this->assertSame([
                'No', 'Sekolah Asal (Terstandarisasi)', 'Jumlah Pendaftar', 'Persentase (%)',
            ], $recap[2]);

            $schoolRows = array_slice($recap, 3);
            $this->assertCount(5, $schoolRows);
            $this->assertSame(
                ['SMP MELATI JAYA', 'SMP NEGERI 2 BANDUNG', 'SMP DARUL HIKAM', 'SMP DARUL HIKAM INTEGRATED SCHOOL', 'TIDAK DIISI'],
                array_column($schoolRows, 1),
            );
            $this->assertSame([3, 2, 1, 1, 1], array_map('intval', array_column($schoolRows, 2)));
            $this->assertEqualsWithDelta(0.375, (float) $schoolRows[0][3], 0.0000001);
            $this->assertEqualsWithDelta(1.0, array_sum(array_column($schoolRows, 3)), 0.0000001);
            $this->assertSame(8, array_sum(array_column($schoolRows, 2)));

            $mainRows = $sheets['Data Pendaftaran'];
            $originalSchoolColumn = array_search('Sekolah Asal', $mainRows[0], true);
            $this->assertIsInt($originalSchoolColumn);
            $this->assertCount(9, $mainRows);
            $this->assertSame(' SMP Melati   Jaya ', $mainRows[1][$originalSchoolColumn]);
            $this->assertSame(' SMP Melati   Jaya ', Registration::findOrFail($includedIds[0])->previous_school);

            // XLSX format: percent values are numeric with a 0.00% number format,
            // and the new recap is filterable without modifying existing sheets.
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($result['path']) === true);
            try {
                $recapXml = $zip->getFromName('xl/worksheets/sheet5.xml');
                $stylesXml = $zip->getFromName('xl/styles.xml');
                $this->assertIsString($recapXml);
                $this->assertIsString($stylesXml);
                $this->assertStringContainsString('ref="A4:D9"', $recapXml);
                $this->assertStringContainsString('numFmtId="10"', $stylesXml);
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($result['path']);
        }
    }

    public function test_empty_filtered_export_still_contains_school_recap_headers(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        $result = app(RegistrationExcelExportService::class)->export(
            Registration::query()->whereRaw('1 = 0'),
            $actor,
        );

        try {
            $this->assertSame(0, $result['count']);
            $sheets = $this->readWorkbook($result['path']);
            $this->assertStringContainsString('Total Pendaftar: 0', $sheets['rekap_sekolah'][1][0]);
            $this->assertSame('Sekolah Asal (Terstandarisasi)', $sheets['rekap_sekolah'][2][1]);
            $this->assertCount(3, $sheets['rekap_sekolah']);
        } finally {
            @unlink($result['path']);
        }
    }

    /** @return array<string, list<list<mixed>>> */
    private function readWorkbook(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);
        $result = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            $result[$sheet->getName()] = $rows;
        }

        $reader->close();

        return $result;
    }
}
