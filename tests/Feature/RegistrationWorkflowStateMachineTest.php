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
use App\Services\RegistrationNumberService;
use App\Services\RegistrationWorkflowService;
use App\Services\UnitConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $this->assertNull($registration->registration_number);

        $workflow = app(RegistrationWorkflowService::class);
        $workflow->validateData($registration, $staff, true);
        $registration->refresh();

        $this->assertSame('payment', $registration->current_stage);
        $this->assertSame('valid', $registration->data_validation_status);
        $this->assertNull($registration->registration_number);

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
        $this->assertSame('documents', $registration->current_stage);
        $this->assertSame('payment_verified', $registration->status);
        $this->assertSame('REG-SMA-20262027-0001', $registration->registration_number);
        $this->assertSame('KARTU-SMA-20262027-0001', $registration->applicant_card_number);
        $this->assertNotNull($registration->applicant_card_issued_at);
        $this->assertSame($staff->id, $registration->applicant_card_issued_by);
        $this->assertSame(
            'REG-SMA-20262027-0001',
            $registration->receipts()->firstOrFail()->details['registration_number'],
        );

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
        $this->assertNull($registration->admissionOffer()->first());
        $this->assertSame('published', $announcement->status);
        $this->assertNotNull($announcement->published_at);
        $this->assertNull($announcement->fresh()->email_sent_at);
        Queue::assertPushed(SendAnnouncementPublishedMail::class, fn ($job) => $job->announcementId === $announcement->id);
    }

    public function test_documents_first_workflow_waits_to_issue_card_until_documents_are_verified(): void
    {
        Queue::fake();

        [$registration, $staff, $unit] = $this->registrationFixture();
        $configurationService = app(UnitConfigurationService::class);
        $draft = $configurationService->draft($unit, $staff);
        $data = $draft->toArray();
        $data['workflow_blocks'] = [
            ['key' => 'documents'],
            ['key' => 'applicant_card'],
        ];
        $configuration = $configurationService->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);

        VirtualAccount::create([
            'unit_id' => $unit->id,
            'bank' => 'MANDIRI',
            'va_number' => '8432572999',
            'status' => 'available',
        ]);

        $workflow = app(RegistrationWorkflowService::class);
        $workflow->validateData($registration, $staff, true);

        $payment = $registration->payments()->firstOrFail();
        $payment->update([
            'proof_path' => 'payments/'.$registration->id.'/proof.pdf',
            'proof_original_name' => 'proof.pdf',
            'proof_mime_type' => 'application/pdf',
            'proof_sha256' => str_repeat('b', 64),
            'proof_malware_scan_status' => 'clean',
            'proof_security_scanned_at' => now(),
        ]);

        $workflow->markPaymentUploaded($payment);
        $workflow->verifyPayment($payment, $staff, true);
        $registration->refresh();

        $this->assertSame('documents', $registration->current_stage);
        $this->assertNotNull($registration->registration_number);
        $this->assertNull($registration->applicant_card_number);

        foreach (RegistrationWorkflowService::REQUIRED_DOCUMENTS as $type) {
            Document::create([
                'registration_id' => $registration->id,
                'type' => $type,
                'file_path' => 'documents/'.$registration->id.'/'.$type.'.pdf',
                'original_name' => $type.'.pdf',
                'file_type' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 1024,
                'sha256' => hash('sha256', $type.'documents-first'),
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

        $this->assertSame('applicant_card', $registration->current_stage);
        $this->assertNull($registration->applicant_card_number);

        $workflow->issueApplicantCard($registration, $staff);
        $registration->refresh();

        $this->assertSame('selection', $registration->current_stage);
        $this->assertNotNull($registration->applicant_card_number);
        $this->assertNotNull($registration->applicant_card_issued_at);
    }

    public function test_registration_number_sequence_is_independent_per_unit(): void
    {
        $sd = Unit::create(['name' => 'Sekolah Dasar', 'code' => 'SD', 'is_active' => true]);
        $dc = Unit::create(['name' => 'Daycare', 'code' => 'DC', 'is_active' => true]);
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $sdOpening = RegistrationOpening::create([
            'unit_id' => $sd->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 385000,
            'status' => 'open',
        ]);
        $dcOpening = RegistrationOpening::create([
            'unit_id' => $dc->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 200000,
            'status' => 'open',
        ]);

        $make = function (Unit $unit, RegistrationOpening $opening, string $nik, string $name) use ($user): Registration {
            return Registration::create([
                'user_id' => $user->id,
                'unit_id' => $unit->id,
                'registration_opening_id' => $opening->id,
                'registrant_type' => 'parent',
                'registrant_relationship' => 'father',
                'nik' => $nik,
                'full_name' => $name,
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2018-01-01',
                'home_address' => 'Bandung',
                'current_stage' => 'payment_verification',
                'lifecycle_status' => 'active',
                'payment_verified_at' => now(),
            ]);
        };

        $sdFirst = $make($sd, $sdOpening, '3273010101010401', 'SD Pertama');
        $dcFirst = $make($dc, $dcOpening, '3273010101010402', 'DC Pertama');
        $sdSecond = $make($sd, $sdOpening, '3273010101010403', 'SD Kedua');

        $allocator = app(RegistrationNumberService::class);

        DB::transaction(fn () => $allocator->assign($sdFirst));
        DB::transaction(fn () => $allocator->assign($dcFirst));
        DB::transaction(fn () => $allocator->assign($sdSecond));

        $this->assertSame('REG-SD-20262027-0001', $sdFirst->fresh()->registration_number);
        $this->assertSame('REG-DC-20262027-0001', $dcFirst->fresh()->registration_number);
        $this->assertSame('REG-SD-20262027-0002', $sdSecond->fresh()->registration_number);
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

    public function test_stale_transition_cannot_continue_after_lifecycle_is_cancelled(): void
    {
        [$registration, $staff] = $this->registrationFixture();
        $registration->update(['current_stage' => 'documents']);
        $staleRequest = $registration->fresh();
        $registration->changeLifecycle('cancelled', $staff, 'Duplikasi pendaftaran');

        try {
            $staleRequest->transitionTo('documents', ['documents_completed_at' => now()]);
            $this->fail('Stale workflow request must not update a cancelled registration.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_stage', $exception->errors());
        }

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'current_stage' => 'documents',
            'lifecycle_status' => 'cancelled',
            'documents_completed_at' => null,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'registration_id' => $registration->id,
            'event' => 'registration.stage_transition',
        ]);
    }

    public function test_same_stage_transition_saves_changed_attributes(): void
    {
        $this->freezeTime();
        [$registration] = $this->registrationFixture();
        $registration->update(['current_stage' => 'documents']);

        $registration->transitionTo('documents', ['documents_completed_at' => now()]);

        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'current_stage' => 'documents',
            'documents_completed_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'registration_id' => $registration->id,
            'event' => 'registration.stage_transition',
        ]);
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
