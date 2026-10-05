<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ContinuationCandidateResource;
use App\Models\ContinuationCandidate;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\ContinuationCandidateImportService;
use App\Services\ContinuationCandidateMatcher;
use App\Services\ContinuationCandidateTemplateService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ContinuationCandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_nik_and_birth_date_match_is_scoped_to_unit_and_academic_year(): void
    {
        $unit = Unit::create([
            'name' => 'SMP Tujuan',
            'code' => 'SMP-T',
            'is_active' => true,
        ]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);

        $candidate = ContinuationCandidate::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'source_school_name' => 'SD Asal',
            'source_key' => hash('sha256', 'candidate-1'),
            'nik' => '3273010101010001',
            'birth_date' => '2014-01-01',
            'full_name' => 'Siswa Terusan',
            'prefill_data' => ['full_name' => 'Siswa Terusan'],
            'is_active' => true,
        ]);

        $matcher = app(ContinuationCandidateMatcher::class);

        $this->assertTrue(
            $matcher->match($opening->uuid, '3273010101010001', '2014-01-01')?->is($candidate)
        );
        $this->assertNull($matcher->match($opening->uuid, '3273010101010001', '2014-01-02'));

        $otherOpening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);

        $this->assertNull($matcher->match($otherOpening->uuid, '3273010101010001', '2014-01-01'));
    }

    public function test_import_reads_two_level_parent_headers_and_keeps_raw_data(): void
    {
        $unit = Unit::create([
            'name' => 'SMP Import',
            'code' => 'SMP-I',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $path = tempnam(sys_get_temp_dir(), 'continuation_').'.csv';
        file_put_contents($path, implode("\n", [
            'Nama,NIK,Tanggal Lahir,Sekolah Asal,Data Ayah,,,,,,Data Ibu,,,,,',
            ',,,,Nama,Tahun Lahir,Jenjang Pendidikan,Pekerjaan,Penghasilan,NIK,Nama,Tahun Lahir,Jenjang Pendidikan,Pekerjaan,Penghasilan,NIK',
            'Budi,3273010101010001,01/01/2014,SD Contoh,Ayah Budi,1980,S1,Wiraswasta,5000000,3273010101010002,Ibu Budi,1982,SMA,Guru,4000000,3273010101010003',
        ]));

        try {
            $result = app(ContinuationCandidateImportService::class)->import(
                $path,
                $unit->id,
                '2026/2027',
                $user->id,
                'terusan.csv',
            );
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['created']);

        $candidate = ContinuationCandidate::query()->firstOrFail();

        $this->assertSame('3273010101010001', $candidate->nik);
        $this->assertSame('SD Contoh', $candidate->source_school_name);
        $this->assertSame('Ayah Budi', $candidate->prefill_data['parentInfo.father_name']);
        $this->assertSame('S1', $candidate->prefill_data['parentInfo.father_education']);
        $this->assertSame('Ibu Budi', $candidate->prefill_data['parentInfo.mother_name']);
        $this->assertSame('Budi', $candidate->raw_data['nama']);
    }

    public function test_admin_unit_can_generate_a_safe_xlsx_import_template(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Unit Template Terusan',
            'code' => 'SMP-TPL',
            'is_active' => true,
        ]);

        $adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $adminUnit->assignRole('admin_unit');

        $template = app(ContinuationCandidateTemplateService::class)->generate($adminUnit);

        $this->assertFileExists($template['path']);
        $this->assertSame('template-import-terusan-smp-tpl.xlsx', $template['filename']);

        $reader = new Reader();
        $reader->open($template['path']);

        $sheets = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }

                $sheets[$sheet->getName()] = $rows;
            }
        } finally {
            $reader->close();
            @unlink($template['path']);
        }

        $this->assertSame(['Data Terusan', 'Petunjuk', 'Contoh'], array_keys($sheets));
        $this->assertSame('Nama', $sheets['Data Terusan'][0][0]);
        $this->assertSame('Tanggal Lahir', $sheets['Data Terusan'][0][5]);
        $this->assertSame('NIK', $sheets['Data Terusan'][0][6]);
        $this->assertSame('Sekolah Asal', $sheets['Data Terusan'][0][23]);
        $this->assertSame('Data Ayah', $sheets['Data Terusan'][0][24]);
        $this->assertSame('Nama', $sheets['Data Terusan'][1][24]);
        $this->assertCount(2, $sheets['Data Terusan']);
        $this->assertSame('Budi Santoso', $sheets['Contoh'][2][0]);
        $this->assertStringContainsString('NIK 16 digit', implode(' ', $sheets['Petunjuk'][2]));
    }

    public function test_resource_is_available_to_admin_unit_but_not_tu(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Unit Terusan',
            'code' => 'TERUSAN',
            'is_active' => true,
        ]);

        $adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $adminUnit->assignRole('admin_unit');

        $tu = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $tu->assignRole('tu');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($adminUnit);
        $this->assertTrue(ContinuationCandidateResource::canViewAny());
        $this->get(ContinuationCandidateResource::getUrl())
            ->assertOk()
            ->assertSeeText('Download Template XLSX')
            ->assertSeeText('Import Data');

        $this->actingAs($tu);
        $this->assertFalse(ContinuationCandidateResource::canViewAny());
    }
}
