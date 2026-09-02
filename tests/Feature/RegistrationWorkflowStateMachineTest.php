<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementPublishedMail;
use App\Jobs\SendVirtualAccountMail;
use App\Models\AdmissionTest;
use App\Models\Document;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\RegistrationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationWorkflowStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_spmb_journey_runs_only_through_legal_stage_transitions(): void
    {
        Queue::fake();

        [$registration, $staff, $unit] = $this->registrationFixture();

        AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik',
            'code' => 'AKD',
            'is_required' => true,
            'is_active' => true,
            'passing_score' => 70,
        ]);

        VirtualAccount::create([
            'unit_id' => $unit->id,
            'bank' => 'MANDIRI',
            'va_number' => '8432572985',
            'status' => 'available',
        ]);

        $workflow = app(RegistrationWorkflowService::class);
        $workflow->validateData($registration, $staff, true);
        $registration->refresh();

        $this->assertSame('payment', $registration->current_stage);
        $this->assertSame('valid', $registration->data_validation_status);

        $payment = $registration->payments()->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('8432572985', $payment->va_number);
        $this->assertSame('350000.00', $payment->amount);
        $this->assertSame('assigned', VirtualAccount::query()->firstOrFail()->status);
        Queue::assertPushed(SendVirtualAccountMail::class, fn ($job) => $job->paymentId === $payment->id);

        $payment->update([
            'proof_path' => 'payments/'.$registration->id.'/proof.pdf',
            'proof_original_name' => 'proof.pdf',
            'proof_mime_type' => 'application/pdf',
            'proof_sha256' => str_repeat('a', 64),
            'proof_malware_scan_status' => 'clean',
            'proof_security_scanned_at' => now(),
        ]);
        $workflow->markPaymentUploaded($payment);
        $registration->refresh();
        $this->assertSame('payment_verification', $registration->current_stage);

        $workflow->verifyPayment($payment, $staff, true);
        $registration->refresh();
        $this->assertSame('applicant_card', $registration->current_stage);
        $this->assertSame('payment_verified', $registration->status);

        $workflow->issueApplicantCard($registration, $staff);
        $registration->refresh();
        $this->assertSame('documents', $registration->current_stage);
        $this->assertNotNull($registration->applicant_card_number);

        foreach (RegistrationWorkflowService::REQUIRED_DOCUMENTS as $type) {
            Document::create([
                'registration_id' => $registration->id,
                'type' => $type,
                'file_path' => 'documents/'.$registration->id.'/'.$type.'.pdf',
                'original_name' => $type.'.pdf',
                'file_type' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 1024,
                'sha256' => hash('sha256', $type),
                'malware_scan_status' => 'clean',
                'security_scanned_at' => now(),
                'is_verified' => true,
                'verified_at' => now(),
                'verified_by' => $staff->id,
            ]);
        }

        $registration->transitionTo('document_verification', ['documents_completed_at' => now()]);
        $this->assertTrue($workflow->refreshDocumentStage($registration));
        $registration->refresh();
        $this->assertSame('tests', $registration->current_stage);

        $testResult = $registration->testResults()->firstOrFail();
        $workflow->recordTestResult($testResult, $staff, [
            'status' => 'completed',
            'score' => 90,
            'result' => 'pass',
            'notes' => 'Lulus tes akademik',
        ]);
        $registration->refresh();
        $this->assertSame('selection', $registration->current_stage);

        $selection = $workflow->decide($registration, $staff, 'accepted', 90, 'Diterima');
        $registration->refresh();
        $this->assertSame('accepted', $selection->decision);
        $this->assertSame('announcement', $registration->current_stage);

        $announcement = $workflow->publish(
            $registration,
            $staff,
            'Pengumuman Hasil SPMB',
            'Selamat, calon siswa dinyatakan diterima.',
        );

        $registration->refresh();
        $this->assertSame('completed', $registration->current_stage);
        $this->assertSame('accepted', $registration->status);
        $this->assertNotNull($registration->accepted_at);
        $this->assertSame('published', $announcement->status);
        $this->assertNotNull($announcement->published_at);
        $this->assertNull($announcement->fresh()->email_sent_at);
        Queue::assertPushed(SendAnnouncementPublishedMail::class, fn ($job) => $job->announcementId === $announcement->id);
    }

    public function test_workflow_service_rejects_skipping_required_stages(): void
    {
        [$registration, $staff] = $this->registrationFixture();
        $registration->forceFill(['current_stage' => 'payment', 'status' => 'payment_pending'])->save();

        try {
            app(RegistrationWorkflowService::class)->decide($registration, $staff, 'accepted', 100);
            $this->fail('Invalid workflow transition should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_stage', $exception->errors());
        }

        $registration->refresh();
        $this->assertSame('payment', $registration->current_stage);
        $this->assertDatabaseMissing('selections', ['registration_id' => $registration->id]);
    }

    public function test_state_machine_rejects_direct_illegal_transition(): void
    {
        [$registration] = $this->registrationFixture();
        $registration->forceFill(['current_stage' => 'payment'])->save();
        $this->expectException(ValidationException::class);
        $registration->transitionTo('selection');
    }

    public function test_stale_concurrent_transition_cannot_overwrite_newer_stage(): void
    {
        [$registration] = $this->registrationFixture();
        $registration->forceFill(['current_stage' => 'payment'])->save();

        $firstRequest = Registration::query()->findOrFail($registration->id);
        $staleRequest = Registration::query()->findOrFail($registration->id);
        $firstRequest->transitionTo('payment_verification');

        try {
            $staleRequest->transitionTo('payment_verification');
            $this->fail('Stale workflow request should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_stage', $exception->errors());
        }

        $this->assertSame('payment_verification', Registration::query()->findOrFail($registration->id)->current_stage);
    }

    public function test_non_active_registration_cannot_continue_workflow_and_can_be_reactivated(): void
    {
        [$registration, $staff] = $this->registrationFixture();
        $registration->changeLifecycle('cancelled', $staff, 'Duplikasi pendaftaran');

        try {
            app(RegistrationWorkflowService::class)->validateData($registration, $staff, true);
            $this->fail('Cancelled registration must not continue workflow.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lifecycle_status', $exception->errors());
        }

        $registration->refresh()->changeLifecycle('active', $staff);
        $this->assertSame('active', $registration->fresh()->lifecycle_status);
    }

    private function registrationFixture(): array
    {
        $unit = Unit::create(['name' => 'SMA Taruna Bakti', 'code' => 'SMA', 'is_active' => true]);
        $applicant = User::factory()->create(['role' => 'user', 'is_active' => true, 'email' => 'parent@example.test']);
        $staff = User::factory()->create(['role' => 'admin', 'is_active' => true, 'unit_id' => $unit->id]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'pathway' => 'Reguler',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);

        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => '3273010101010001',
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);

        return [$registration, $staff, $unit, $opening, $applicant];
    }
}
