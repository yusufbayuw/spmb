<?php

namespace Tests\Feature;

use App\Models\CertificationAttempt;
use App\Models\CertificationProgram;
use App\Models\PracticalRunAction;
use App\Models\PracticalScenario;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\CertificationService;
use App\Services\PracticalSandboxService;
use App\Services\TrainingService;
use App\Services\TrainingMasteryService;
use Database\Seeders\ShieldSeeder;
use Database\Seeders\TrainingCertificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CertificationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_theory_attempt_and_certificate_remain_immutable_after_master_data_changes(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create([
            'name' => 'Nama Saat Ujian',
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $this->completeTraining($user, 'TRN-ADMIN');

        $program = CertificationProgram::query()
            ->where('code', 'SCA')
            ->where('version', '1.0')
            ->firstOrFail();

        $program->practicalScenarios()->update(['is_active' => false]);
        $program->update([
            'question_count' => 5,
            'shuffle_questions' => false,
            'shuffle_options' => false,
        ]);

        $service = app(CertificationService::class);
        $attempt = $service->start($user, $program)->load('attemptQuestions');

        $this->assertCount(5, $attempt->attemptQuestions);
        $this->assertSame('SCA', $attempt->program_code_snapshot);
        $this->assertSame('1.0', $attempt->program_version_snapshot);

        $firstSnapshot = $attempt->attemptQuestions->first();
        $sourceQuestion = $firstSnapshot->sourceQuestion;

        $originalQuestionText = $firstSnapshot->question;
        $originalCorrectAnswer = $firstSnapshot->correct_answer;
        $originalProgramName = $attempt->program_name_snapshot;

        $sourceQuestion->update([
            'question' => 'PERTANYAAN SUDAH DIUBAH SETELAH ATTEMPT DIMULAI',
            'correct_answer' => '__changed__',
        ]);

        $program->update([
            'name' => 'Nama Program Baru',
            'version' => '9.9',
            'passing_score' => 100,
        ]);

        $result = $service->submit(
            $attempt,
            $user,
            $this->correctAnswers($attempt),
        );

        $this->assertSame('passed', $result->status);

        $snapshotAfterEdit = $result->attemptQuestions
            ->firstWhere('id', $firstSnapshot->id);

        $this->assertSame($originalQuestionText, $snapshotAfterEdit->question);
        $this->assertSame($originalCorrectAnswer, $snapshotAfterEdit->correct_answer);

        $certificate = $result->certification()->firstOrFail();

        $this->assertSame('Nama Saat Ujian', $certificate->recipient_name_snapshot);
        $this->assertSame($originalProgramName, $certificate->program_name_snapshot);
        $this->assertSame('1.0', $certificate->program_version_snapshot);

        $user->update(['name' => 'Nama Setelah Sertifikat']);
        $program->update(['name' => 'Nama Program Setelah Sertifikat']);

        $this->get(route('certificates.verify', $certificate))
            ->assertOk()
            ->assertSee('Nama Saat Ujian')
            ->assertSee($originalProgramName)
            ->assertSee('Versi 1.0')
            ->assertDontSee('Nama Setelah Sertifikat')
            ->assertDontSee('Nama Program Setelah Sertifikat');
    }

    public function test_practical_action_and_assertion_snapshots_are_immune_to_admin_edits(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin_unit');

        $this->completeTraining($user, 'TRN-UNIT');

        $program = CertificationProgram::query()
            ->where('code', 'SCUA')
            ->where('version', '1.0')
            ->firstOrFail();

        $program->update([
            'question_count' => 1,
            'shuffle_questions' => false,
            'shuffle_options' => false,
        ]);

        $program->practicalScenarios()
            ->where('code', '!=', 'SCUA-OPENING-01')
            ->update(['is_active' => false]);

        $service = app(CertificationService::class);
        $theory = $service->start($user, $program);
        $theory = $service->submit($theory, $user, $this->correctAnswers($theory));

        $this->assertSame('passed', $theory->status);
        $this->assertNull($theory->certification);

        $scenario = PracticalScenario::query()
            ->where('certification_program_id', $program->id)
            ->where('code', 'SCUA-OPENING-01')
            ->with(['program', 'records', 'actions', 'assertions'])
            ->firstOrFail();

        $sandbox = app(PracticalSandboxService::class);
        $run = $sandbox->start($user, $scenario)
            ->load(['runActions', 'runAssertions']);

        $runAction = $run->runActions->firstWhere('code', 'pause_opening');
        $runAssertion = $run->runAssertions->firstWhere('code', 'paused');

        $this->assertSame('paused', $runAction->mutation['status']);
        $this->assertSame('paused', $runAssertion->config['expected']);

        $scenario->actions()
            ->where('code', 'pause_opening')
            ->firstOrFail()
            ->update([
                'mutation' => [
                    'status' => 'closed',
                    'visible_to_applicants' => false,
                ],
            ]);

        $scenario->assertions()
            ->where('code', 'paused')
            ->firstOrFail()
            ->update([
                'config' => [
                    'entity_type' => 'opening',
                    'entity_key' => 'gelombang-1',
                    'path' => 'status',
                    'expected' => 'closed',
                ],
            ]);

        $sandbox->performAction(
            $user,
            $run,
            PracticalRunAction::query()->findOrFail($runAction->id),
        );

        $result = $sandbox->submit($user, $run);

        $this->assertTrue($result->passed);
        $this->assertSame(100.0, (float) $result->score);
        $this->assertTrue($user->fresh()->hasValidCertification('SCUA'));

        $this->assertSame(
            'paused',
            PracticalRunAction::query()->findOrFail($runAction->id)->mutation['status'],
        );
    }

    public function test_exam_policy_limits_question_set_time_and_attempts(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $this->completeTraining($user, 'TRN-ADMIN');

        $program = CertificationProgram::query()
            ->where('code', 'SCA')
            ->where('version', '1.0')
            ->firstOrFail();

        $program->practicalScenarios()->update(['is_active' => false]);
        $program->update([
            'question_count' => 3,
            'time_limit_minutes' => 30,
            'max_attempts' => 1,
            'cooldown_hours' => 0,
            'shuffle_questions' => false,
        ]);

        $service = app(CertificationService::class);
        $attempt = $service->start($user, $program)->load('attemptQuestions');

        $this->assertCount(3, $attempt->attemptQuestions);
        $this->assertSame(3, $attempt->question_count_snapshot);
        $this->assertSame(30, $attempt->time_limit_minutes_snapshot);
        $this->assertNotNull($attempt->expires_at);

        $wrongAnswers = $attempt->attemptQuestions
            ->mapWithKeys(fn ($question): array => [$question->id => '__wrong__'])
            ->all();

        $result = $service->submit($attempt, $user, $wrongAnswers);
        $this->assertSame('failed', $result->status);

        $this->expectException(ValidationException::class);
        $service->start($user, $program);
    }

    public function test_expired_theory_attempt_fails_with_zero_score(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $this->completeTraining($user, 'TRN-ADMIN');

        $program = CertificationProgram::query()
            ->where('code', 'SCA')
            ->where('version', '1.0')
            ->firstOrFail();

        $program->practicalScenarios()->update(['is_active' => false]);
        $program->update([
            'question_count' => 1,
            'time_limit_minutes' => 1,
            'max_attempts' => 3,
        ]);

        $service = app(CertificationService::class);
        $attempt = $service->start($user, $program);
        $attempt->update(['expires_at' => now()->subMinute()]);

        $result = $service->submit(
            $attempt->fresh(),
            $user,
            $this->correctAnswers($attempt),
        );

        $this->assertSame('failed', $result->status);
        $this->assertSame(0.0, (float) $result->score);
    }

    public function test_program_code_can_have_multiple_versions(): void
    {
        $this->seed(TrainingCertificationSeeder::class);

        $trainingV1 = TrainingProgram::query()
            ->where('code', 'TRN-ADMIN')
            ->where('version', '1.0')
            ->firstOrFail();

        $trainingV2 = TrainingProgram::query()->create([
            'code' => 'TRN-ADMIN',
            'name' => 'Pelatihan Administrator SPMB v2',
            'description' => 'Versi kedua.',
            'target_role' => 'super_admin',
            'version' => '2.0',
            'is_active' => false,
            'sort_order' => 1,
        ]);

        CertificationProgram::query()->create([
            'code' => 'SCA',
            'name' => 'SPMB Certified Administrator v2',
            'description' => 'Versi kedua.',
            'target_role' => 'super_admin',
            'version' => '2.0',
            'passing_score' => 85,
            'question_count' => 30,
            'time_limit_minutes' => 45,
            'max_attempts' => 3,
            'cooldown_hours' => 24,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'theory_weight' => 40,
            'practical_weight' => 60,
            'practical_passing_score' => 80,
            'valid_months' => 24,
            'training_program_id' => $trainingV2->id,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $this->assertNotSame($trainingV1->id, $trainingV2->id);
        $this->assertSame(2, TrainingProgram::query()->where('code', 'TRN-ADMIN')->count());
        $this->assertSame(2, CertificationProgram::query()->where('code', 'SCA')->count());
    }

    public function test_revocation_records_actor_reason_and_changes_public_status(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create([
            'name' => 'Peserta Sertifikasi',
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        $this->completeTraining($user, 'TRN-ADMIN');

        $program = CertificationProgram::query()
            ->where('code', 'SCA')
            ->where('version', '1.0')
            ->firstOrFail();

        $program->practicalScenarios()->update(['is_active' => false]);
        $program->update(['question_count' => 1, 'shuffle_questions' => false]);

        $service = app(CertificationService::class);
        $attempt = $service->start($user, $program);
        $result = $service->submit($attempt, $user, $this->correctAnswers($attempt));
        $certificate = $result->certification()->firstOrFail();

        $service->revoke(
            $certificate,
            $user,
            'Sertifikat dicabut untuk pengujian integritas.',
            ['source' => 'automated-test'],
        );

        $certificate->refresh();

        $this->assertSame('revoked', $certificate->status);
        $this->assertNotNull($certificate->revoked_at);
        $this->assertSame($user->id, $certificate->revoked_by_user_id);
        $this->assertSame(
            'Sertifikat dicabut untuk pengujian integritas.',
            $certificate->revocation_reason,
        );

        $this->get(route('certificates.verify', $certificate))
            ->assertOk()
            ->assertSee('DICABUT')
            ->assertDontSee('Sertifikat dicabut untuk pengujian integritas.');
    }

    private function completeTraining(User $user, string $code): void
    {
        $program = TrainingProgram::query()
            ->where('code', $code)
            ->where('version', '1.0')
            ->with('modules.lessons', 'modules.assessment.questions')
            ->firstOrFail();

        foreach ($program->modules as $module) {
            foreach ($module->lessons as $lesson) {
                app(TrainingService::class)->completeLesson($user, $lesson);
            }

            $assessment = $module->assessment;
            if ($assessment?->is_active && $assessment?->is_required) {
                $attempt = app(TrainingMasteryService::class)->start($user, $assessment);
                $attempt->loadMissing('attemptQuestions');

                $answers = $attempt->attemptQuestions
                    ->pluck('correct_answer', 'id')
                    ->mapWithKeys(fn ($answer, $id): array => [(int) $id => (string) $answer])
                    ->all();

                app(TrainingMasteryService::class)->submit($attempt, $user, $answers);
            }
        }
    }

    private function correctAnswers(CertificationAttempt $attempt): array
    {
        $attempt->loadMissing('attemptQuestions');

        return $attempt->attemptQuestions
            ->pluck('correct_answer', 'id')
            ->mapWithKeys(fn ($answer, $id): array => [(int) $id => (string) $answer])
            ->all();
    }
}
