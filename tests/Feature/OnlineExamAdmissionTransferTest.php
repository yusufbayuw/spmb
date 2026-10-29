<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\OnlineExam;
use App\Models\OnlineExamAttempt;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OnlineExamAdmissionTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_approval_transfers_final_score_and_advances_only_existing_workflow(): void
    {
        [$attempt, $registration, $admin, $exam] = $this->scenario();
        $service = app(OnlineExamResultTransferService::class);

        $this->assertArrayHasKey($registration->id, $service->eligibleRegistrations($attempt, $admin));
        $this->assertSame('tests', $registration->fresh()->current_stage);

        $transfer = $service->transfer($attempt, $registration->id, $admin);
        $this->assertSame($attempt->id, $transfer->online_exam_attempt_id);
        $result = AdmissionTestResult::query()->where('registration_id', $registration->id)->firstOrFail();
        $this->assertSame($result->id, $transfer->admission_test_result_id);
        $this->assertSame('completed', $result->status);
        $this->assertSame('pass', $result->result);
        $this->assertEquals(100, (float) $result->score);
        $this->assertSame('selection', $registration->fresh()->current_stage);
        $this->assertEquals(100, (float) $registration->selection()->value('final_score'));
        $this->assertSame('pending', $registration->selection()->value('decision'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'online_exam.result_transferred']);
        $this->assertSame(1, OnlineExamResultTransfer::count());

        $this->expectException(ValidationException::class);
        $service->transfer($attempt, $registration->id, $admin);
    }

    public function test_transfer_refuses_unrelated_registration_or_existing_final_result(): void
    {
        [$attempt, $registration, $admin, $exam] = $this->scenario();
        $other = $registration->replicate();
        $other->registration_number = 'CBT-OTHER-001';
        $other->nik = '3273010101010999';
        $other->user_id = User::factory()->create()->id;
        $other->save();
        AdmissionTestResult::create([
            'registration_id' => $other->id,
            'admission_test_id' => $exam->admission_test_id,
            'status' => 'unbooked', 'result' => 'pending',
        ]);

        $service = app(OnlineExamResultTransferService::class);
        try {
            $service->transfer($attempt, $other->id, $admin);
            $this->fail('Other users must not receive a CBT score.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('registration_id', $e->errors());
        }

        $registration->testResults()->firstOrFail()->update([
            'status' => 'completed', 'result' => 'pass', 'score' => 88,
            'assessed_at' => now(), 'assessed_by' => $admin->id,
        ]);
        try {
            $service->transfer($attempt, $registration->id, $admin);
            $this->fail('Existing finalized scores must not be overwritten.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transfer', $e->errors());
        }
        $this->assertSame(0, OnlineExamResultTransfer::count());
    }

    public function test_pending_attempt_and_revoked_assignment_cannot_transfer(): void
    {
        [$attempt, $registration, $admin] = $this->scenario();
        $attempt->update(['status' => 'pending_review', 'percentage' => null, 'passed' => null]);
        $service = app(OnlineExamResultTransferService::class);
        $this->assertSame([], $service->eligibleRegistrations($attempt->fresh(), $admin));
        try {
            $service->transfer($attempt, $registration->id, $admin);
            $this->fail('Unreviewed score cannot be transferred.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transfer', $e->errors());
        }

        $attempt->update(['status' => 'graded', 'percentage' => 100, 'passed' => true]);
        $attempt->assignment->update(['is_active' => false]);
        try {
            $service->transfer($attempt, $registration->id, $admin);
            $this->fail('Revoked access cannot transfer.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transfer', $e->errors());
        }
        $this->assertSame(0, OnlineExamResultTransfer::count());
    }

    public function test_mapped_test_must_belong_to_same_unit_and_cannot_change_after_attempts(): void
    {
        [$attempt, $registration, $admin, $exam] = $this->scenario();
        $foreign = Unit::create(['name' => 'Foreign CBT', 'code' => 'CBT-F', 'is_active' => true]);
        $foreignTest = AdmissionTest::create([
            'unit_id' => $foreign->id, 'name' => 'Test B', 'code' => 'TEST-B',
            'result_type' => 'score', 'is_active' => true,
        ]);
        try {
            $exam->update(['admission_test_id' => $foreignTest->id]);
            $this->fail('Cross-unit mapping must not be allowed.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('admission_test_id', $e->errors());
        }
        $this->assertSame((int) $registration->testResults()->value('admission_test_id'),
            (int) $exam->fresh()->admission_test_id);
    }

    /** @return array{OnlineExamAttempt, Registration, User, OnlineExam} */
    private function scenario(): array
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'CBT Admission', 'code' => 'CBT-A', 'is_active' => true]);
        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('pendaftar');

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1', 'registration_fee' => 0, 'status' => 'open',
        ]);
        $test = AdmissionTest::create([
            'unit_id' => $unit->id, 'name' => 'Tes Seleksi CBT', 'code' => 'CBT-ADM',
            'is_active' => true, 'is_required' => true, 'result_type' => 'score',
            'passing_score' => 70,
        ]);
        $config = UnitConfiguration::create([
            'unit_id' => $unit->id, 'version' => 1, 'status' => 'published',
            'payment_enabled' => false, 'documents_enabled' => false,
            'tests_enabled' => true, 'selection_mode' => 'flexible',
            'post_announcement_enabled' => false,
            'fields' => [], 'document_requirements' => [], 're_registration_requirements' => [],
            'test_definitions' => [[
                'id' => $test->id, 'name' => $test->name,
                'is_required' => true, 'result_type' => 'score', 'passing_score' => 70,
            ]],
            'published_at' => now(),
        ]);
        $registration = Registration::create([
            'user_id' => $user->id, 'unit_id' => $unit->id,
            'unit_configuration_id' => $config->id,
            'registration_opening_id' => $opening->id,
            'registration_number' => 'CBT-REG-001', 'registrant_type' => 'parent',
            'registrant_relationship' => 'father', 'nik' => '3273010101010201',
            'full_name' => 'Peserta CBT', 'gender' => 'L', 'birth_place' => 'Bandung',
            'birth_date' => '2018-01-01', 'home_address' => 'Bandung',
            'status' => 'submitted', 'submitted_at' => now(),
            'current_stage' => 'tests', 'lifecycle_status' => 'active',
            'data_validation_status' => 'valid',
        ]);
        AdmissionTestResult::create([
            'registration_id' => $registration->id, 'admission_test_id' => $test->id,
            'status' => 'unbooked', 'result' => 'pending',
        ]);
        $exam = OnlineExam::create([
            'unit_id' => $unit->id, 'title' => 'Paket CBT Seleksi',
            'admission_test_id' => $test->id,
            'shuffle_questions' => false, 'shuffle_options' => false,
        ]);
        $question = OnlineExamQuestion::create([
            'unit_id' => $unit->id, 'type' => 'true_false', 'prompt' => 'Benar?',
            'options' => [], 'grading_config' => ['correct' => '1'],
            'points' => 1, 'is_active' => true,
        ]);
        OnlineExamItem::create([
            'online_exam_id' => $exam->id, 'online_exam_question_id' => $question->id,
        ]);
        $exam->update(['is_published' => true]);
        $assignment = app(OnlineExamEngine::class)->assign($exam, $user, $staff);
        $attempt = app(OnlineExamEngine::class)->start($assignment, $user);
        $item = $attempt->items->sole();
        app(OnlineExamEngine::class)->save($attempt, $user, $item->id, '1');
        $attempt = app(OnlineExamEngine::class)->submit($attempt, $user);

        return [$attempt, $registration, $staff, $exam];
    }
}
