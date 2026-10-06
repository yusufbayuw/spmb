<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ContinuationCandidateResource;
use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\ContinuationCandidate;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\ContinuationCandidateExportService;
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
            ',,,,,,,Nama,NIK,Nama,NIK',
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

    public function test_legacy_candidate_without_prefill_data_still_autofills_from_raw_data(): void
    {
        $candidate = new ContinuationCandidate([
            'source_school_name' => 'SD Legacy',
            'full_name' => 'Siswa Legacy',
            'prefill_data' => null,
            'raw_data' => [
                'jk' => 'P',
                'tempat lahir' => 'Bandung',
                'alamat' => 'Jl. Legacy',
                'data ayah nama' => 'Ayah Legacy',
                'data ibu nama' => 'Ibu Legacy',
            ],
        ]);

        $prefill = app(ContinuationCandidateMatcher::class)->prefill($candidate);

        $this->assertSame('Siswa Legacy', $prefill['full_name']);
        $this->assertSame('P', $prefill['gender']);
        $this->assertSame('Bandung', $prefill['birth_place']);
        $this->assertSame('Jl. Legacy', $prefill['home_address']);
        $this->assertSame('SD Legacy', $prefill['previous_school']);
        $this->assertSame('Ayah Legacy', $prefill['parentInfo.father_name']);
        $this->assertSame('Ibu Legacy', $prefill['parentInfo.mother_name']);
    }

    public function test_exact_production_like_identity_autofills_via_livewire_lifecycle_hooks(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Sekolah Menengah Pertama',
            'code' => 'SMP',
            'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'registration_fee' => 805000,
            'status' => 'open',
        ]);
        RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Akademik',
            'is_active' => true,
        ]);
        ContinuationCandidate::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'source_school_name' => 'SD Contoh Utama',
            'source_key' => hash('sha256', 'production-like-aizhar'),
            'nik' => '3273023105150000',
            'birth_date' => '2015-05-31',
            'full_name' => 'Aizhar Ilrachim Solihin',
            'prefill_data' => [
                'full_name' => 'Aizhar Ilrachim Solihin',
                'previous_school' => 'SD Contoh Utama',
            ],
            'is_active' => true,
        ]);

        $applicant = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $applicant->assignRole('pendaftar');

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->setActionData(['accepted' => true])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->set('data.nik', '3273023105150000')
            ->set('data.birth_date', '2015-05-31')
            ->assertSet('data.full_name', 'Aizhar Ilrachim Solihin')
            ->assertSet('data.previous_school', 'SD Contoh Utama');
    }

    public function test_matcher_accepts_iso_datetime_birth_date_state(): void
    {
        $unit = Unit::create([
            'name' => 'SMP ISO Date',
            'code' => 'SMP-ISO',
            'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);

        $candidate = ContinuationCandidate::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'source_school_name' => 'SD ISO',
            'source_key' => hash('sha256', 'iso-date-candidate'),
            'nik' => '3273023105150000',
            'birth_date' => '2015-05-31',
            'full_name' => 'Aizhar Ilrachim Solihin',
            'prefill_data' => ['full_name' => 'Aizhar Ilrachim Solihin'],
            'is_active' => true,
        ]);

        $matched = app(ContinuationCandidateMatcher::class)->match(
            $opening->uuid,
            '3273023105150000',
            '2015-05-31T00:00:00+07:00',
        );

        $this->assertTrue($matched?->is($candidate));
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

    public function test_exported_correction_file_contains_stable_id_and_current_identity(): void
    {
        $unit = Unit::create([
            'name' => 'Unit Export Koreksi',
            'code' => 'KOREKSI',
            'is_active' => true,
        ]);

        $adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $candidate = ContinuationCandidate::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'source_school_name' => 'SD Koreksi',
            'source_key' => hash('sha256', 'export-correction'),
            'nik' => '3273010101010093',
            'birth_date' => '2014-01-01',
            'full_name' => 'Siswa Koreksi',
            'prefill_data' => [
                'full_name' => 'Siswa Koreksi',
                'gender' => 'L',
                'previous_school' => 'SD Koreksi',
            ],
            'is_active' => true,
        ]);

        $export = app(ContinuationCandidateExportService::class)->export(
            $adminUnit,
            $unit->id,
            '2027/2028',
        );

        $this->assertFileExists($export['path']);
        $this->assertSame(1, $export['count']);

        $reader = new Reader();
        $reader->open($export['path']);
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getName() !== 'Data Terusan') {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();

                    if (count($rows) >= 2) {
                        break;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
            @unlink($export['path']);
        }

        $this->assertSame('ID Data Terusan', $rows[0][0]);
        $this->assertSame('Tanggal Lahir', $rows[0][6]);
        $this->assertSame('NIK', $rows[0][7]);
        $this->assertSame($candidate->uuid, $rows[1][0]);
        $this->assertSame('01/01/2014', $rows[1][6]);
        $this->assertSame('3273010101010093', $rows[1][7]);
    }

    public function test_reimporting_exported_row_can_change_nik_and_birth_date_without_creating_duplicate(): void
    {
        $unit = Unit::create([
            'name' => 'Unit Reimport Koreksi',
            'code' => 'REIMPORT',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $candidate = ContinuationCandidate::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'source_school_name' => 'SD Sebelum Koreksi',
            'source_key' => hash('sha256', 'before-correction'),
            'nik' => '3273010101010093',
            'birth_date' => '2014-01-01',
            'full_name' => 'Siswa Sebelum Koreksi',
            'prefill_data' => ['full_name' => 'Siswa Sebelum Koreksi'],
            'is_active' => true,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'continuation_correction_').'.csv';
        file_put_contents($path, implode("\n", [
            'ID Data Terusan,Nama,NIK,Tanggal Lahir,Sekolah Asal,Aktif',
            $candidate->uuid.',Siswa Setelah Koreksi,3273010202020094,02/02/2014,SD Setelah Koreksi,Ya',
        ]));

        try {
            $result = app(ContinuationCandidateImportService::class)->import(
                $path,
                $unit->id,
                '2027/2028',
                $user->id,
                'data-terusan-koreksi.csv',
            );
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseCount('continuation_candidates', 1);

        $candidate->refresh();

        $this->assertSame('3273010202020094', $candidate->nik);
        $this->assertSame('2014-02-02', $candidate->birth_date?->format('Y-m-d'));
        $this->assertSame('Siswa Setelah Koreksi', $candidate->full_name);
        $this->assertSame('SD Setelah Koreksi', $candidate->source_school_name);
        $this->assertSame(
            hash('sha256', 'match|'.$unit->id.'|2027/2028|3273010202020094|2014-02-02'),
            $candidate->source_key,
        );
    }

    public function test_resource_is_available_and_editable_for_admin_unit_and_tu_with_unit_scope(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Unit Terusan',
            'code' => 'TERUSAN',
            'is_active' => true,
        ]);
        $otherUnit = Unit::create([
            'name' => 'Unit Terusan Lain',
            'code' => 'TERUSAN-LAIN',
            'is_active' => true,
        ]);

        $ownCandidate = ContinuationCandidate::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'source_school_name' => 'SD Contoh',
            'source_key' => hash('sha256', 'editable-own-candidate'),
            'nik' => '3273010101010091',
            'birth_date' => '2014-01-01',
            'full_name' => 'Siswa Terusan Editable',
            'prefill_data' => ['full_name' => 'Siswa Terusan Editable'],
            'is_active' => true,
        ]);
        $otherCandidate = ContinuationCandidate::create([
            'unit_id' => $otherUnit->id,
            'academic_year' => '2027/2028',
            'source_school_name' => 'SD Unit Lain',
            'source_key' => hash('sha256', 'editable-other-candidate'),
            'nik' => '3273010101010092',
            'birth_date' => '2014-01-02',
            'full_name' => 'Siswa Unit Lain',
            'prefill_data' => ['full_name' => 'Siswa Unit Lain'],
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
        $this->assertTrue(ContinuationCandidateResource::canEdit($ownCandidate));
        $this->assertFalse(ContinuationCandidateResource::canEdit($otherCandidate));
        $this->get(ContinuationCandidateResource::getUrl())
            ->assertOk()
            ->assertSeeText('Download Data Koreksi')
            ->assertSeeText('Download Template XLSX')
            ->assertSeeText('Import Data')
            ->assertSeeText('Edit');

        $this->actingAs($tu);
        $this->assertTrue(ContinuationCandidateResource::canViewAny());
        $this->assertTrue(ContinuationCandidateResource::canEdit($ownCandidate));
        $this->assertFalse(ContinuationCandidateResource::canEdit($otherCandidate));
        $this->assertFalse(ContinuationCandidateResource::canCreate());
        $this->get(ContinuationCandidateResource::getUrl())
            ->assertOk()
            ->assertSeeText('Download Data Koreksi')
            ->assertSeeText('Edit')
            ->assertDontSeeText('Import Data');
    }
}
