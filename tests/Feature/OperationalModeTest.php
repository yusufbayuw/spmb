<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\StudyProgramResource;
use App\Models\EducationLevel;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Services\UnitConfigurationService;
use App\Support\SpmbOperationalMode;
use Database\Seeders\RegistrationOpeningSeeder;
use Database\Seeders\StudyProgramSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class OperationalModeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::MIXED);

        parent::tearDown();
    }

    public function test_invalid_mode_falls_back_to_mixed(): void
    {
        config()->set('spmb.operations.mode', 'UNKNOWN');

        $this->assertSame(SpmbOperationalMode::MIXED, SpmbOperationalMode::mode());
        $this->assertTrue(SpmbOperationalMode::allowsK12());
        $this->assertTrue(SpmbOperationalMode::allowsHigherEducation());
    }

    public function test_k12_seeders_create_only_k12_operational_data(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::K12);

        $this->seed(UnitSeeder::class);
        $this->seed(StudyProgramSeeder::class);
        $this->seed(RegistrationOpeningSeeder::class);

        $this->assertSame(6, Unit::query()->count());
        $this->assertFalse(Unit::query()->where('institution_type', 'university')->exists());
        $this->assertSame(0, StudyProgram::query()->count());
        $this->assertSame(6, RegistrationOpening::query()->count());
        $this->assertFalse(StudyProgramResource::shouldRegisterNavigation());
    }

    public function test_higher_education_seeders_create_only_university_operational_data(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);

        $this->seed(UnitSeeder::class);
        $this->seed(StudyProgramSeeder::class);
        $this->seed(RegistrationOpeningSeeder::class);

        $this->assertSame(['TBU'], Unit::query()->pluck('code')->all());
        $this->assertSame(7, StudyProgram::query()->count());
        $this->assertSame(7, RegistrationOpening::query()->count());
        $this->assertTrue(StudyProgramResource::shouldRegisterNavigation());
    }

    public function test_higher_education_programs_have_independent_editable_workflow_templates(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);

        $level = EducationLevel::query()->where('code', 'S1')->firstOrFail();
        $unit = Unit::create([
            'name' => 'Taruna Bakti University',
            'code' => 'TBU',
            'institution_type' => 'university',
            'is_active' => true,
        ]);

        $defaultProgram = StudyProgram::create([
            'unit_id' => $unit->id,
            'education_level_id' => $level->id,
            'code' => 'MNJ',
            'name' => 'Manajemen',
            'degree_level' => 'S1',
            'is_active' => true,
        ]);

        $customWorkflow = collect(StudyProgram::defaultWorkflowSteps())
            ->map(function (array $step): array {
                if ($step['stage'] === 'data_validation') {
                    $step['label'] = 'Verifikasi Awal Informatika';
                    $step['description'] = 'Pemeriksaan awal khusus Program Studi Informatika.';
                }

                return $step;
            })
            ->all();

        $customProgram = StudyProgram::create([
            'unit_id' => $unit->id,
            'education_level_id' => $level->id,
            'code' => 'IF',
            'name' => 'Informatika',
            'degree_level' => 'S1',
            'workflow_steps' => $customWorkflow,
            'is_active' => true,
        ]);

        $defaultLabels = collect($defaultProgram->fresh()->configuredWorkflowSteps())->pluck('label', 'stage');
        $customLabels = collect($customProgram->fresh()->configuredWorkflowSteps())->pluck('label', 'stage');

        $this->assertSame('Pembayaran Registrasi', $defaultLabels['admission_offer']);
        $this->assertSame('Daftar Ulang', $defaultLabels['re_registration']);
        $this->assertSame('Perwalian', $defaultLabels['enrollment']);
        $this->assertSame('Validasi Data', $defaultLabels['data_validation']);
        $this->assertSame('Verifikasi Awal Informatika', $customLabels['data_validation']);
        $this->assertTrue(app(UnitConfigurationService::class)->defaults($unit)['post_announcement_enabled']);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'study_program_id' => $customProgram->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
        ]);
        $registration = Registration::create([
            'user_id' => \App\Models\User::factory()->create()->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'nik' => '3273010101010001',
            'full_name' => 'Calon Mahasiswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2008-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
        ]);

        $this->assertSame('Verifikasi Awal Informatika', $registration->stageLabel());
        $this->assertSame('Verifikasi Awal Informatika', $registration->progressStages()['data_validation']);
        $this->assertSame('Pemeriksaan awal khusus Program Studi Informatika.', $registration->currentStageDescription());
    }

    public function test_higher_education_helpdesk_does_not_fall_back_to_foundation_contact(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);
        config()->set('spmb.portal.foundation_email', 'yayasan@example.test');
        config()->set('spmb.portal.foundation_address', 'Alamat Yayasan');

        $unit = Unit::create([
            'name' => 'Taruna Bakti University',
            'code' => 'TBU',
            'institution_type' => 'university',
            'public_contact_name' => 'PMB Taruna Bakti University',
            'public_email' => 'pmb@example.test',
            'is_active' => true,
        ]);

        $html = Blade::render('<x-admissions.helpdesk :unit="$unit" />', ['unit' => $unit]);

        $this->assertStringContainsString('PMB Taruna Bakti University', $html);
        $this->assertStringContainsString('pmb@example.test', $html);
        $this->assertStringNotContainsString('yayasan@example.test', $html);
        $this->assertStringNotContainsString('Alamat Yayasan', $html);
    }

    public function test_switching_mode_hides_existing_data_without_deleting_it(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::MIXED);
        $this->seed(UnitSeeder::class);
        $this->seed(StudyProgramSeeder::class);
        $this->seed(RegistrationOpeningSeeder::class);

        $school = Unit::query()->where('code', 'SMA')->firstOrFail();
        $university = Unit::query()->where('code', 'TBU')->firstOrFail();
        $universityOpening = RegistrationOpening::query()
            ->where('unit_id', $university->id)
            ->firstOrFail();

        $this->assertSame(7, Unit::query()->count());

        config()->set('spmb.operations.mode', SpmbOperationalMode::K12);

        $this->assertSame(7, Unit::query()->count(), 'Mode changes must never delete existing records.');
        $this->assertSame(6, Unit::query()->forOperationalMode()->count());
        $this->assertFalse(Unit::query()->forOperationalMode()->whereKey($university->id)->exists());
        $this->assertTrue(Unit::query()->forOperationalMode()->whereKey($school->id)->exists());

        $this->get(route('admissions.unit', ['unit' => $university->code]))->assertNotFound();
        $this->get(route('admissions.show', $universityOpening))->assertNotFound();
        $this->get(route('admissions.qr', $universityOpening))->assertNotFound();
    }

    public function test_homepage_copy_and_filters_follow_higher_education_mode(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);
        $this->seed(UnitSeeder::class);
        $this->seed(StudyProgramSeeder::class);
        $this->seed(RegistrationOpeningSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Penerimaan Mahasiswa Baru')
            ->assertSee('Pilih jenjang dan program studi')
            ->assertSee('S1')
            ->assertSee('D3')
            ->assertDontSee('jenjang=SMA', false)
            ->assertDontSee('jenjang=DC', false);
    }
}
