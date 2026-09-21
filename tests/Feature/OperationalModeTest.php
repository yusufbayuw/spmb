<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\StudyProgramResource;
use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Support\SpmbOperationalMode;
use Database\Seeders\RegistrationOpeningSeeder;
use Database\Seeders\StudyProgramSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
