<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Support\SpmbOperationalMode;
use Database\Seeders\EducationLevelSeeder;
use Database\Seeders\PublicContentSeeder;
use Database\Seeders\RegistrationPathwaySeeder;
use Database\Seeders\StudyProgramSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::MIXED);

        parent::tearDown();
    }

    public function test_k12_public_content_seeder_populates_only_k12_content_and_is_idempotent(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::K12);

        $this->seed(EducationLevelSeeder::class);
        $this->seed(UnitSeeder::class);
        $this->seed(StudyProgramSeeder::class);
        $this->seed(RegistrationPathwaySeeder::class);
        $this->seed(PublicContentSeeder::class);

        $this->assertSame(6, Unit::query()->forOperationalMode()->count());
        $this->assertSame(0, StudyProgram::query()->count());
        $this->assertFalse(Unit::query()->forOperationalMode()->whereNull('public_headline')->exists());
        $this->assertTrue(Faq::query()->whereNull('study_program_id')->exists());
        $this->assertFalse(Faq::query()->whereNotNull('study_program_id')->exists());

        $faqCount = Faq::query()->count();

        $this->seed(PublicContentSeeder::class);

        $this->assertSame($faqCount, Faq::query()->count());
    }

    public function test_higher_education_public_content_seeder_populates_program_content_and_contextual_faqs(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);

        $this->seed(EducationLevelSeeder::class);
        $this->seed(UnitSeeder::class);
        $this->seed(StudyProgramSeeder::class);
        $this->seed(RegistrationPathwaySeeder::class);
        $this->seed(PublicContentSeeder::class);

        $tbu = Unit::query()->where('code', 'TBU')->firstOrFail();

        $this->assertNotNull($tbu->public_headline);
        $this->assertNotNull($tbu->public_body);
        $this->assertSame(7, StudyProgram::query()->where('unit_id', $tbu->id)->count());
        $this->assertFalse(
            StudyProgram::query()
                ->where('unit_id', $tbu->id)
                ->where(function ($query): void {
                    $query->whereNull('public_headline')
                        ->orWhereNull('public_body')
                        ->orWhereNull('study_duration')
                        ->orWhereNull('public_highlights')
                        ->orWhereNull('public_target_audiences');
                })
                ->exists(),
        );
        $this->assertSame(
            7,
            Faq::query()->where('unit_id', $tbu->id)->whereNotNull('study_program_id')->count(),
        );
        $this->assertTrue(
            Faq::query()->where('unit_id', $tbu->id)->whereNotNull('registration_pathway_id')->exists(),
        );
    }
}
