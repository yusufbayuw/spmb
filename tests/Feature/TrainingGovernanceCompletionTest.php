<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\CertificationProgram;
use App\Models\TrainingModuleAttempt;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\UserCertification;
use App\Services\CertificateArtifactService;
use App\Services\CertificationAccessService;
use App\Services\TrainingAnalyticsService;
use App\Services\TrainingMasteryService;
use App\Services\TrainingService;
use Database\Seeders\ShieldSeeder;
use Database\Seeders\TrainingCertificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainingGovernanceCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_sequence_and_module_mastery_gate_progression(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $program = TrainingProgram::query()
            ->where('code', 'TRN-ADMIN')
            ->where('version', '1.0')
            ->with('modules.lessons', 'modules.assessment.questions')
            ->firstOrFail();

        $training = app(TrainingService::class);
        $mastery = app(TrainingMasteryService::class);
        $enrollment = $training->enroll($user, $program);

        $firstModule = $program->modules->first();
        $secondModule = $program->modules->skip(1)->first();
        $lessons = $firstModule->lessons->values();

        $this->expectException(ValidationException::class);
        $training->completeLesson($user, $lessons[1]);
    }

    public function test_module_mastery_unlocks_next_module_and_training_completion_requires_checkpoints(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $program = TrainingProgram::query()
            ->where('code', 'TRN-ADMIN')
            ->where('version', '1.0')
            ->with('modules.lessons', 'modules.assessment.questions')
            ->firstOrFail();

        $training = app(TrainingService::class);
        $mastery = app(TrainingMasteryService::class);
        $enrollment = $training->enroll($user, $program);

        $firstModule = $program->modules->first();
        $secondModule = $program->modules->skip(1)->first();

        foreach ($firstModule->lessons as $lesson) {
            $training->completeLesson($user, $lesson);
        }

        $enrollment->refresh();

        $this->assertFalse($training->moduleComplete($enrollment, $firstModule));
        $this->assertFalse($training->moduleUnlocked($user, $secondModule, $enrollment));

        $attempt = $mastery->start($user, $firstModule->assessment);
        $result = $mastery->submit($attempt, $user, $this->correctMasteryAnswers($attempt));

        $this->assertSame('passed', $result->status);
        $this->assertTrue($training->moduleComplete($enrollment->fresh(), $firstModule));
        $this->assertTrue($training->moduleUnlocked($user, $secondModule, $enrollment->fresh()));
        $this->assertNotSame('completed', $enrollment->fresh()->status);

        $this->completeTraining($user, $program);

        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertSame(100, $training->percentage($enrollment->fresh()));
    }

    public function test_mastery_attempt_uses_immutable_question_snapshot(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $program = TrainingProgram::query()
            ->where('code', 'TRN-ADMIN')
            ->where('version', '1.0')
            ->with('modules.lessons', 'modules.assessment.questions')
            ->firstOrFail();

        $training = app(TrainingService::class);
        $module = $program->modules->first();

        foreach ($module->lessons as $lesson) {
            $training->completeLesson($user, $lesson);
        }

        $attempt = app(TrainingMasteryService::class)->start($user, $module->assessment)
            ->load('attemptQuestions');

        $snapshot = $attempt->attemptQuestions->first();
        $originalQuestion = $snapshot->question;
        $originalAnswer = $snapshot->correct_answer;

        $snapshot->sourceQuestion->update([
            'question' => 'PERTANYAAN MASTER SUDAH DIUBAH',
            'correct_answer' => '__changed__',
        ]);

        $result = app(TrainingMasteryService::class)
            ->submit($attempt, $user, $this->correctMasteryAnswers($attempt));

        $this->assertSame('passed', $result->status);
        $freshSnapshot = $result->attemptQuestions->firstWhere('id', $snapshot->id);
        $this->assertSame($originalQuestion, $freshSnapshot->question);
        $this->assertSame($originalAnswer, $freshSnapshot->correct_answer);
    }

    public function test_certification_enforcement_modes_are_safe_by_default_and_configurable(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $settings = AppSetting::query()->create([
            'portal_name' => 'SPMB',
            'organization_name' => 'Institusi',
            'certification_enforcement_mode' => 'off',
        ]);

        $access = app(CertificationAccessService::class);

        $this->assertTrue($access->allowsResourceMutation($user, 'user', 'update'));

        $settings->update(['certification_enforcement_mode' => 'warning']);
        $this->assertTrue($access->allowsResourceMutation($user, 'user', 'update'));
        $this->assertNotNull($access->warning($user));

        $settings->update(['certification_enforcement_mode' => 'sensitive_actions']);
        $this->assertFalse($access->allowsResourceMutation($user, 'user', 'update'));
        $this->assertTrue($access->allowsResourceMutation($user, 'document', 'update'));

        $settings->update(['certification_enforcement_mode' => 'full_role']);
        $this->assertFalse($access->allowsResourceMutation($user, 'document', 'update'));

        $program = CertificationProgram::query()
            ->where('code', 'SCA')
            ->where('version', '1.0')
            ->firstOrFail();

        UserCertification::query()->create([
            'user_id' => $user->id,
            'certification_program_id' => $program->id,
            'certificate_number' => 'SPMB-SCA-TEST-001',
            'verification_code' => (string) Str::uuid(),
            'score' => 90,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $this->assertTrue($access->allowsResourceMutation($user->fresh(), 'user', 'update'));
    }

    public function test_expiry_reminder_is_idempotent_per_threshold(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        AppSetting::query()->create([
            'portal_name' => 'SPMB',
            'organization_name' => 'Institusi',
            'certification_expiry_reminder_days' => [30, 14, 7, 1],
        ]);

        $program = CertificationProgram::query()
            ->where('code', 'SCA')
            ->where('version', '1.0')
            ->firstOrFail();

        $certificate = UserCertification::query()->create([
            'user_id' => $user->id,
            'certification_program_id' => $program->id,
            'certificate_number' => 'SPMB-SCA-TEST-EXPIRY',
            'verification_code' => (string) Str::uuid(),
            'program_code_snapshot' => 'SCA',
            'program_version_snapshot' => '1.0',
            'score' => 90,
            'issued_at' => now()->subYear(),
            'expires_at' => now()->addDays(7),
            'status' => 'active',
        ]);

        $this->artisan('spmb:remind-certification-expiry')->assertExitCode(0);
        $this->artisan('spmb:remind-certification-expiry')->assertExitCode(0);

        $certificate->refresh();

        $this->assertArrayHasKey('7', $certificate->expiry_reminders_sent);
        $this->assertCount(1, $certificate->expiry_reminders_sent);
    }

    public function test_certificate_pdf_artifact_is_tamper_evident(): void
    {
        Storage::fake('local');

        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['name' => 'Peserta Artifact', 'is_active' => true]);
        $user->assignRole('super_admin');

        $program = CertificationProgram::query()
            ->where('code', 'SCA')
            ->where('version', '1.0')
            ->firstOrFail();

        $certificate = UserCertification::query()->create([
            'user_id' => $user->id,
            'certification_program_id' => $program->id,
            'recipient_name_snapshot' => 'Peserta Artifact',
            'recipient_unit_snapshot' => 'Admin Pusat',
            'recipient_role_snapshot' => 'Admin Pusat',
            'program_code_snapshot' => 'SCA',
            'program_name_snapshot' => 'SPMB Certified Administrator',
            'program_version_snapshot' => '1.0',
            'certificate_number' => 'SPMB-SCA-ARTIFACT-001',
            'verification_code' => (string) Str::uuid(),
            'score' => 92,
            'theory_score' => 90,
            'practical_score' => 94,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
            'status' => 'active',
        ]);

        $service = app(CertificateArtifactService::class);
        $certificate = $service->generate($certificate);

        Storage::disk('local')->assertExists($certificate->artifact_path);
        $this->assertTrue($service->verify($certificate));
        $this->assertStringStartsWith(
            '%PDF-1.4',
            Storage::disk('local')->get($certificate->artifact_path),
        );

        $this->get(route('certificates.pdf', $certificate))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        Storage::disk('local')->put(
            $certificate->artifact_path,
            Storage::disk('local')->get($certificate->artifact_path).'TAMPER',
        );

        $this->assertFalse($service->verify($certificate->fresh()));
        $this->get(route('certificates.pdf', $certificate->fresh()))
            ->assertStatus(409);
    }

    public function test_training_analytics_uses_real_training_and_certification_data(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $viewer = User::factory()->create(['is_active' => true]);
        $viewer->assignRole('super_admin');

        $program = TrainingProgram::query()
            ->where('code', 'TRN-ADMIN')
            ->where('version', '1.0')
            ->with('modules.lessons', 'modules.assessment.questions')
            ->firstOrFail();

        $this->completeTraining($viewer, $program);

        $data = app(TrainingAnalyticsService::class)->dashboard($viewer);

        $this->assertGreaterThanOrEqual(1, $data['overview']['staff']);
        $this->assertSame(1, $data['overview']['training_completed']);
        $this->assertNotEmpty($data['programs']);
        $this->assertNotEmpty($data['weak_modules']);
    }

    private function completeTraining(User $user, TrainingProgram $program): void
    {
        $program->loadMissing('modules.lessons', 'modules.assessment.questions');

        foreach ($program->modules as $module) {
            foreach ($module->lessons as $lesson) {
                if (! $lesson->progress()
                    ->whereHas('enrollment', fn ($query) => $query->where('user_id', $user->id))
                    ->where('status', 'completed')
                    ->exists()) {
                    app(TrainingService::class)->completeLesson($user, $lesson);
                }
            }

            $assessment = $module->assessment;
            if ($assessment?->is_active
                && $assessment?->is_required
                && ! app(TrainingMasteryService::class)->passed($user->id, $assessment->id)) {
                $attempt = app(TrainingMasteryService::class)->start($user, $assessment);
                app(TrainingMasteryService::class)->submit(
                    $attempt,
                    $user,
                    $this->correctMasteryAnswers($attempt),
                );
            }
        }
    }

    private function correctMasteryAnswers(TrainingModuleAttempt $attempt): array
    {
        $attempt->loadMissing('attemptQuestions');

        return $attempt->attemptQuestions
            ->pluck('correct_answer', 'id')
            ->mapWithKeys(fn ($answer, $id): array => [(int) $id => (string) $answer])
            ->all();
    }
}
