<?php

namespace Tests\Feature;

use App\Models\CertificationProgram;
use App\Models\CertificationQuestion;
use App\Models\PracticalScenario;
use App\Models\TrainingLesson;
use App\Models\TrainingModuleAssessment;
use App\Models\TrainingModuleQuestion;
use App\Models\TrainingModule;
use App\Models\TrainingProgram;
use Database\Seeders\TrainingCertificationSeeder;
use Database\Seeders\TrainingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCurriculumSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_curriculum_is_seeded_with_expected_scope(): void
    {
        $this->seed(TrainingSeeder::class);

        $this->assertSame(3, TrainingProgram::query()->whereIn('code', ['TRN-ADMIN', 'TRN-UNIT', 'TRN-TU'])->count());

        $this->assertProgramCounts('TRN-ADMIN', 8, 24);
        $this->assertProgramCounts('TRN-UNIT', 12, 36);
        $this->assertProgramCounts('TRN-TU', 9, 27);

        $this->assertCertificationCounts('SCA', 50, 5);
        $this->assertCertificationCounts('SCUA', 78, 8);
        $this->assertCertificationCounts('SCAO', 52, 6);

        $this->assertSame(29, TrainingModuleAssessment::query()->count());
        $this->assertSame(87, TrainingModuleQuestion::query()->count());

        $this->assertDatabaseCount('registrations', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('practical_runs', 0);
        $this->assertDatabaseCount('practical_sandbox_records', 0);
    }

    public function test_curriculum_seeder_is_idempotent_and_preserves_admin_edits(): void
    {
        $this->seed(TrainingSeeder::class);

        $countsBefore = $this->curriculumCounts();

        $lesson = TrainingLesson::query()->whereNotNull('seed_key')->firstOrFail();
        $question = CertificationQuestion::query()->whereNotNull('seed_key')->firstOrFail();
        $scenario = PracticalScenario::query()->firstOrFail();
        $masteryQuestion = TrainingModuleQuestion::query()->whereNotNull('seed_key')->firstOrFail();

        $lesson->update([
            'title' => 'Judul Materi yang Diedit Admin',
            'content' => '<p>Konten khusus hasil edit Admin Pusat.</p>',
        ]);
        $question->update([
            'question' => 'Pertanyaan yang telah disunting Admin Pusat?',
            'explanation' => 'Penjelasan khusus admin.',
        ]);
        $scenario->update([
            'instructions' => 'Instruksi scenario telah disunting oleh Admin Pusat.',
        ]);
        $masteryQuestion->update([
            'question' => 'Checkpoint yang telah disunting Admin Pusat?',
            'explanation' => 'Penjelasan checkpoint khusus admin.',
        ]);

        $this->seed(TrainingSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $this->assertSame($countsBefore, $this->curriculumCounts());

        $this->assertSame(
            'Judul Materi yang Diedit Admin',
            TrainingLesson::query()->findOrFail($lesson->id)->title,
        );
        $this->assertSame(
            '<p>Konten khusus hasil edit Admin Pusat.</p>',
            TrainingLesson::query()->findOrFail($lesson->id)->content,
        );
        $this->assertSame(
            'Pertanyaan yang telah disunting Admin Pusat?',
            CertificationQuestion::query()->findOrFail($question->id)->question,
        );
        $this->assertSame(
            'Penjelasan khusus admin.',
            CertificationQuestion::query()->findOrFail($question->id)->explanation,
        );
        $this->assertSame(
            'Instruksi scenario telah disunting oleh Admin Pusat.',
            PracticalScenario::query()->findOrFail($scenario->id)->instructions,
        );
        $this->assertSame(
            'Checkpoint yang telah disunting Admin Pusat?',
            TrainingModuleQuestion::query()->findOrFail($masteryQuestion->id)->question,
        );
        $this->assertSame(
            'Penjelasan checkpoint khusus admin.',
            TrainingModuleQuestion::query()->findOrFail($masteryQuestion->id)->explanation,
        );
    }

    public function test_seed_keys_are_unique_within_their_parent_scope(): void
    {
        $this->seed(TrainingSeeder::class);

        $moduleDuplicate = TrainingModule::query()
            ->selectRaw('training_program_id, seed_key, COUNT(*) as aggregate')
            ->whereNotNull('seed_key')
            ->groupBy('training_program_id', 'seed_key')
            ->having('aggregate', '>', 1)
            ->exists();

        $lessonDuplicate = TrainingLesson::query()
            ->selectRaw('training_module_id, seed_key, COUNT(*) as aggregate')
            ->whereNotNull('seed_key')
            ->groupBy('training_module_id', 'seed_key')
            ->having('aggregate', '>', 1)
            ->exists();

        $questionDuplicate = CertificationQuestion::query()
            ->selectRaw('certification_program_id, seed_key, COUNT(*) as aggregate')
            ->whereNotNull('seed_key')
            ->groupBy('certification_program_id', 'seed_key')
            ->having('aggregate', '>', 1)
            ->exists();

        $this->assertFalse($moduleDuplicate);
        $this->assertFalse($lessonDuplicate);
        $this->assertFalse($questionDuplicate);
    }

    private function assertProgramCounts(string $code, int $modules, int $lessons): void
    {
        $program = TrainingProgram::query()->where('code', $code)->firstOrFail();

        $this->assertSame($modules, $program->modules()->count());
        $this->assertSame(
            $lessons,
            TrainingLesson::query()
                ->whereHas('module', fn ($query) => $query->where('training_program_id', $program->id))
                ->count(),
        );
    }

    private function assertCertificationCounts(string $code, int $questions, int $scenarios): void
    {
        $program = CertificationProgram::query()->where('code', $code)->firstOrFail();

        $this->assertSame($questions, $program->questions()->count());
        $this->assertSame($scenarios, $program->practicalScenarios()->count());
    }

    private function curriculumCounts(): array
    {
        return [
            'programs' => TrainingProgram::query()->count(),
            'modules' => TrainingModule::query()->count(),
            'lessons' => TrainingLesson::query()->count(),
            'questions' => CertificationQuestion::query()->count(),
            'scenarios' => PracticalScenario::query()->count(),
            'module_assessments' => TrainingModuleAssessment::query()->count(),
            'module_questions' => TrainingModuleQuestion::query()->count(),
        ];
    }
}
