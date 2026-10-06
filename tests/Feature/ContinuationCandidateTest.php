<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ContinuationCandidateResource;
use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\ContinuationCandidate;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\ContinuationCandidateImportService;
use App\Services\ContinuationCandidateMatcher;
use App\Services\ContinuationCandidateTemplateService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_applicant_form_starts_with_nik_and_birth_date_and_prefills_continuation_data(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SMP Tujuan Form',
            'code' => 'SMP-FORM',
            'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);
        RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);
        ContinuationCandidate::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'source_school_name' => 'SD Asal Form',
            'source_key' => hash('sha256', 'candidate-form'),
            'nik' => '3273010101010009',
            'birth_date' => '2014-01-01',
            'full_name' => 'Siswa Terusan Form',
            'prefill_data' => [
                'full_name' => 'Siswa Terusan Form',
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'previous_school' => 'SD Asal Form',
                'parentInfo.father_name' => 'Ayah Terusan',
                'parentInfo.mother_name' => 'Ibu Terusan',
            ],
            'is_active' => true,
        ]);

        $applicant = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $applicant->assignRole('pendaftar');

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        $page = Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->assertSeeInOrder([
                'Identifikasi Peserta',
                'NIK',
                'Tanggal Lahir',
                'Pilihan Pendaftaran',
                'Identitas Calon Siswa / Mahasiswa',
            ])
            ->setActionData(['accepted' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $page
            ->set('data.nik', '3273010101010009')
            ->set('data.birth_date', '2014-01-01')
            ->assertSet('data.full_name', 'Siswa Terusan Form')
            ->assertSet('data.gender', 'L')
            ->assertSet('data.birth_place', 'Bandung')
            ->assertSet('data.previous_school', 'SD Asal Form')
            ->assertSet('data.parentInfo.father_name', 'Ayah Terusan')
            ->assertSet('data.parentInfo.mother_name', 'Ibu Terusan');
    }

    public function test_imported_continuation_data_prefills_real_applicant_form_end_to_end(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SMP Terusan E2E',
            'code' => 'SMP-E2E',
            'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);
        RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);

        $importer = User::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'continuation_e2e_').'.csv';
        file_put_contents($path, implode("\n", [
            'Nama,NIK,Tanggal Lahir,Sekolah Asal,JK,Tempat Lahir,Alamat,Data Ayah,,Data Ibu,',
            ',,,,,,,,Nama,NIK,Nama,NIK',
            'Siswa E2E,3273010101010011,2014-01-01 00:00:00,SD E2E,L,Bandung,Jl. Contoh,Ayah E2E,3273010101010012,Ibu E2E,3273010101010013',
        ]));

        try {
            $result = app(ContinuationCandidateImportService::class)->import(
                $path,
                $unit->id,
                '2027 - 2028',
                $importer->id,
                'terusan-e2e.csv',
            );
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame('2027/2028', ContinuationCandidate::query()->value('academic_year'));

        $applicant = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $applicant->assignRole('pendaftar');

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        $page = Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->setActionData(['accepted' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        // Reverse the usual order too: date first, then NIK.
        $page
            ->set('data.birth_date', '2014-01-01')
            ->set('data.nik', '3273010101010011')
            ->assertSet('data.full_name', 'Siswa E2E')
            ->assertSet('data.gender', 'L')
            ->assertSet('data.birth_place', 'Bandung')
            ->assertSet('data.home_address', 'Jl. Contoh')
            ->assertSet('data.previous_school', 'SD E2E')
            ->assertSet('data.parentInfo.father_name', 'Ayah E2E')
            ->assertSet('data.parentInfo.mother_name', 'Ibu E2E');
    }

    public function test_matcher_uses_latest_active_duplicate_instead_of_failing_silently(): void
    {
        $unit = Unit::create([
            'name' => 'SMP Duplikat Terusan',
            'code' => 'SMP-DUP',
            'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);

        ContinuationCandidate::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'source_school_name' => 'SD Lama',
            'source_key' => hash('sha256', 'duplicate-old'),
            'nik' => '3273010101010015',
            'birth_date' => '2014-01-01',
            'full_name' => 'Nama Lama',
            'prefill_data' => ['full_name' => 'Nama Lama'],
            'is_active' => true,
            'imported_at' => now()->subDay(),
        ]);

        $latest = ContinuationCandidate::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027 / 2028',
            'source_school_name' => 'SD Baru',
            'source_key' => hash('sha256', 'duplicate-new'),
            'nik' => '3273010101010015',
            'birth_date' => '2014-01-01',
            'full_name' => 'Nama Baru',
            'prefill_data' => ['full_name' => 'Nama Baru'],
            'is_active' => true,
            'imported_at' => now(),
        ]);

        $matched = app(ContinuationCandidateMatcher::class)->match(
            $opening->uuid,
            '3273010101010015',
            '2014-01-01',
        );

        $this->assertTrue($matched?->is($latest));
    }

    public function test_import_does_not_silently_keep_unmatchable_continuation_rows(): void
    {
        $unit = Unit::create([
            'name' => 'SMP Import Validation',
            'code' => 'SMP-IV',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $path = tempnam(sys_get_temp_dir(), 'continuation_invalid_').'.csv';
        file_put_contents($path, implode("\n", [
            'Nama,NIK,Tanggal Lahir,Sekolah Asal',
            'Tanpa NIK,,01/01/2014,SD Contoh',
        ]));

        try {
            $result = app(ContinuationCandidateImportService::class)->import(
                $path,
                $unit->id,
                '2027/2028',
                $user->id,
                'terusan-invalid.csv',
            );
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('NIK wajib berupa 16 digit utuh', $result['errors'][0]);
        $this->assertDatabaseCount('continuation_candidates', 0);
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
