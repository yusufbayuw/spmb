<?php

namespace Tests\Feature;

use App\Models\CertificationProgram;
use App\Models\PracticalScenario;
use App\Models\PracticalScenarioAction;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\CertificationService;
use App\Services\PracticalSandboxService;
use App\Services\TrainingService;
use Database\Seeders\ShieldSeeder;
use Database\Seeders\TrainingCertificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCertificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_theory_then_isolated_practical_issues_verifiable_certificate(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin_unit');

        $this->completeTraining($user, 'TRN-UNIT');

        $program = CertificationProgram::query()->where('code', 'SCUA')->firstOrFail();
        $this->limitPracticalTo($program, 'SCUA-OPENING-01');

        $certification = app(CertificationService::class);
        $attempt = $certification->start($user, $program);
        $theoryResult = $certification->submit($attempt, $user, $this->correctAnswers($program));

        $this->assertSame('passed', $theoryResult->status);
        $this->assertNull($theoryResult->certification);
        $this->assertFalse($user->fresh()->hasValidCertification('SCUA'));

        $scenario = PracticalScenario::query()
            ->where('code', 'SCUA-OPENING-01')
            ->with(['program', 'records'])
            ->firstOrFail();

        $sandbox = app(PracticalSandboxService::class);
        $run = $sandbox->start($user, $scenario);

        $pause = PracticalScenarioAction::query()
            ->where('practical_scenario_id', $scenario->id)
            ->where('code', 'pause_opening')
            ->firstOrFail();

        $sandbox->performAction($user, $run, $pause);
        $practicalResult = $sandbox->submit($user, $run);

        $this->assertTrue($practicalResult->passed);
        $this->assertSame(100.0, (float) $practicalResult->score);
        $this->assertTrue($user->fresh()->hasValidCertification('SCUA'));

        $certificate = $user->fresh()->certifications()
            ->whereHas('program', fn ($query) => $query->where('code', 'SCUA'))
            ->firstOrFail();

        $this->assertSame(100.0, (float) $certificate->score);

        $this->assertDatabaseCount('registration_openings', 0);
        $this->assertDatabaseHas('practical_sandbox_records', [
            'practical_run_id' => $run->id,
            'entity_type' => 'opening',
            'entity_key' => 'gelombang-1',
        ]);

        $this->get(route('certificates.verify', $certificate))
            ->assertOk()
            ->assertSee($certificate->certificate_number)
            ->assertSee('VALID');
    }

    public function test_critical_practical_failure_blocks_certificate_even_when_numeric_score_is_high(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('tu');

        $this->completeTraining($user, 'TRN-TU');

        $program = CertificationProgram::query()->where('code', 'SCAO')->firstOrFail();
        $this->limitPracticalTo($program, 'SCAO-VERIFY-01');

        $certification = app(CertificationService::class);
        $attempt = $certification->start($user, $program);
        $certification->submit($attempt, $user, $this->correctAnswers($program));

        $scenario = PracticalScenario::query()
            ->where('code', 'SCAO-VERIFY-01')
            ->with(['program', 'records'])
            ->firstOrFail();

        $sandbox = app(PracticalSandboxService::class);
        $run = $sandbox->start($user, $scenario);

        foreach (['verify_rapor', 'verify_kk', 'verify_payment'] as $actionCode) {
            $action = PracticalScenarioAction::query()
                ->where('practical_scenario_id', $scenario->id)
                ->where('code', $actionCode)
                ->firstOrFail();

            $sandbox->performAction($user, $run, $action);
        }

        $result = $sandbox->submit($user, $run);

        $this->assertFalse($result->passed);
        $this->assertSame('failed', $result->status);
        $this->assertGreaterThanOrEqual(50, (float) $result->score);
        $this->assertFalse($user->fresh()->hasValidCertification('SCAO'));

        $criticalFailure = $result->results
            ->first(fn ($item) => $item->assertion->code === 'kk-rejected');

        $this->assertNotNull($criticalFailure);
        $this->assertTrue($criticalFailure->assertion->is_critical);
        $this->assertFalse($criticalFailure->passed);
    }

    public function test_recertification_requires_a_new_practical_cycle(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin_unit');

        $this->completeTraining($user, 'TRN-UNIT');

        $program = CertificationProgram::query()->where('code', 'SCUA')->firstOrFail();
        $this->limitPracticalTo($program, 'SCUA-OPENING-01');
        $certification = app(CertificationService::class);

        $firstTheory = $certification->start($user, $program);
        $certification->submit($firstTheory, $user, $this->correctAnswers($program));

        $scenario = PracticalScenario::query()
            ->where('code', 'SCUA-OPENING-01')
            ->with(['program', 'records'])
            ->firstOrFail();

        $sandbox = app(PracticalSandboxService::class);
        $firstRun = $sandbox->start($user, $scenario);

        $pause = PracticalScenarioAction::query()
            ->where('practical_scenario_id', $scenario->id)
            ->where('code', 'pause_opening')
            ->firstOrFail();

        $sandbox->performAction($user, $firstRun, $pause);
        $sandbox->submit($user, $firstRun);

        $certificate = $user->fresh()->certifications()
            ->where('certification_program_id', $program->id)
            ->firstOrFail();

        $certificate->update(['expires_at' => now()->subDay()]);

        $secondTheory = $certification->start($user->fresh(), $program->fresh());
        $this->assertSame(2, $secondTheory->attempt_no);

        $secondTheory = $certification->submit(
            $secondTheory,
            $user->fresh(),
            $this->correctAnswers($program->fresh()),
        );

        $this->assertSame('passed', $secondTheory->status);
        $this->assertNull($secondTheory->certification);

        $secondRun = $sandbox->start(
            $user->fresh(),
            $scenario->fresh(['program', 'records']),
        );

        $this->assertSame($secondTheory->id, $secondRun->certification_attempt_id);
        $this->assertNotSame($firstRun->certification_attempt_id, $secondRun->certification_attempt_id);
    }

    public function test_certification_cannot_start_before_required_training_is_completed(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('tu');

        $program = CertificationProgram::query()->where('code', 'SCAO')->firstOrFail();

        $this->assertFalse(app(CertificationService::class)->eligible($user, $program));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CertificationService::class)->start($user, $program);
    }

    private function completeTraining(User $user, string $trainingCode): void
    {
        $training = TrainingProgram::query()
            ->where('code', $trainingCode)
            ->with('modules.lessons')
            ->firstOrFail();

        foreach ($training->modules->flatMap(fn ($module) => $module->lessons) as $lesson) {
            app(TrainingService::class)->completeLesson($user, $lesson);
        }

        $this->assertDatabaseHas('training_enrollments', [
            'training_program_id' => $training->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);
    }

    private function correctAnswers(CertificationProgram $program): array
    {
        return $program->questions()
            ->where('is_active', true)
            ->pluck('correct_answer', 'id')
            ->mapWithKeys(fn ($answer, $id): array => [(int) $id => (string) $answer])
            ->all();
    }

    private function limitPracticalTo(CertificationProgram $program, string $scenarioCode): void
    {
        PracticalScenario::query()
            ->where('certification_program_id', $program->id)
            ->where('code', '!=', $scenarioCode)
            ->update(['is_active' => false]);
    }
}
