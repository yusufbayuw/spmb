<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\OnlineExam;
use App\Models\OnlineExamItem;
use App\Models\OnlineExamQuestion;
use App\Models\OnlineExamResultTransfer;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\OnlineExamEngine;
use App\Services\OnlineExamResultTransferService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineExamOptionalFacilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_reaches_selection_without_any_cbt_packages_or_assignments(): void
    {
        [$registration] = $this->registrationWithoutMandatoryTests();

        $this->assertSame([], $registration->configuredTests());
        $this->assertArrayNotHasKey('tests', $registration->enabledStages());
        $this->assertTrue($registration->canTransitionTo('selection'));
        $registration->transitionTo('selection');

        $this->assertSame('selection', $registration->fresh()->current_stage);
        $this->assertDatabaseCount('online_exams', 0);
        $this->assertDatabaseCount('online_exam_assignments', 0);
        $this->assertDatabaseCount('admission_test_results', 0);
        $this->assertDatabaseCount('online_exam_result_transfers', 0);
    }

    public function test_even_an_assigned_graded_and_linked_cbt_does_not_become_a_mandatory_stage(): void
    {
        $this->seed(ShieldSeeder::class);
        [$registration, $unit, $participant] = $this->registrationWithoutMandatoryTests();

        // A master AdmissionTest may exist and be marked required globally,
        // but the versioned registration config has tests_enabled=false.
        $admissionTest = AdmissionTest::create([
            'unit_id' => $unit->id, 'name' => 'Tes Opsional', 'code' => 'OPT-CBT',
            'is_active' => true, 'is_required' => true, 'result_type' => 'score',
            'passing_score' => 70,
        ]);
        $exam = OnlineExam::create([
            'unit_id' => $unit->id, 'title' => 'Fasilitas CBT',
            'admission_test_id' => $admissionTest->id,
            'shuffle_questions' => false, 'shuffle_options' => false,
        ]);
        $question = OnlineExamQuestion::create([
            'unit_id' => $unit->id, 'type' => 'true_false',
            'prompt' => 'Pilih benar', 'options' => [],
            'grading_config' => ['correct' => '1'],
            'points' => 1, 'is_active' => true,
        ]);
        OnlineExamItem::create([
            'online_exam_id' => $exam->id,
            'online_exam_question_id' => $question->id,
        ]);
        $exam->update(['is_published' => true]);

        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');
        $participant->assignRole('pendaftar');

        $assignment = app(OnlineExamEngine::class)->assign($exam, $participant, $staff);
        $attempt = app(OnlineExamEngine::class)->start($assignment, $participant);
        app(OnlineExamEngine::class)->save($attempt, $participant, $attempt->items->sole()->id, '1');
        $attempt = app(OnlineExamEngine::class)->submit($attempt, $participant);

        $this->assertSame('graded', $attempt->status);
        $this->assertSame([], $registration->fresh()->configuredTests());
        $this->assertArrayNotHasKey('tests', $registration->enabledStages());
        $this->assertSame([], app(OnlineExamResultTransferService::class)
            ->eligibleRegistrations($attempt, $staff));
        $this->assertSame(0, AdmissionTestResult::query()->count());
        $this->assertSame(0, OnlineExamResultTransfer::query()->count());

        $this->assertTrue($registration->canTransitionTo('selection'));
        $registration->transitionTo('selection');
        $this->assertSame('selection', $registration->fresh()->current_stage);
        $this->assertSame(0, AdmissionTestResult::query()->count());
    }

    /** @return array{Registration, Unit, User} */
    private function registrationWithoutMandatoryTests(): array
    {
        $unit = Unit::create([
            'name' => 'Sekolah CBT Opsional', 'code' => 'CBT-OPTIONAL',
            'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027', 'wave' => 'Gelombang 1',
            'registration_fee' => 0, 'status' => 'open',
        ]);
        $configuration = UnitConfiguration::create([
            'unit_id' => $unit->id, 'version' => 1, 'status' => 'published',
            'payment_enabled' => false, 'documents_enabled' => true,
            'tests_enabled' => false, 'selection_mode' => 'flexible',
            'post_announcement_enabled' => false,
            'fields' => [], 'document_requirements' => [],
            'test_definitions' => [], 're_registration_requirements' => [],
            'published_at' => now(),
        ]);
        $participant = User::factory()->create([
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        $registration = Registration::create([
            'user_id' => $participant->id, 'unit_id' => $unit->id,
            'unit_configuration_id' => $configuration->id,
            'registration_opening_id' => $opening->id,
            'registration_number' => 'REG-CBT-OPTIONAL',
            'registrant_type' => 'parent', 'registrant_relationship' => 'father',
            'nik' => '3273010101010901', 'full_name' => 'Peserta Opsional',
            'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2018-01-01',
            'home_address' => 'Bandung', 'status' => 'submitted',
            'submitted_at' => now(), 'current_stage' => 'document_verification',
            'lifecycle_status' => 'active', 'data_validation_status' => 'valid',
        ]);

        return [$registration, $unit, $participant];
    }
}
