<?php

namespace App\Services;

use App\Jobs\SendAnnouncementPublishedMail;
use App\Jobs\SendVirtualAccountMail;
use App\Models\AdmissionTestResult;
use App\Models\Announcement;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Selection;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationWorkflowService
{
    public const REQUIRED_DOCUMENTS = ['report_card', 'family_card', 'birth_certificate', 'photo'];

    public function __construct(private SpmbNotificationService $notifications) {}

    public function validateData(Registration $registration, User $staff, bool $approved, ?string $notes = null): void
    {
        $registration = Registration::query()->findOrFail($registration->id);
        $registration->assertCurrentStage('data_validation');

        $registration->transitionTo(
            $approved ? 'virtual_account' : 'data_validation',
            [
                'data_validation_status' => $approved ? 'valid' : 'revision',
                'data_validation_notes' => $notes,
                'data_validated_by' => $staff->id,
                'data_validated_at' => now(),
                'verified_at' => $approved ? now() : null,
                'status' => $approved ? 'verified' : 'submitted',
            ],
        );

        $this->notifications->dataValidationResult($registration, $approved, $notes);

        if ($approved) {
            $this->assignAvailableVirtualAccount($registration, $staff);
        }
    }

    public function assignAvailableVirtualAccount(Registration $registration, User $staff): ?Payment
    {
        VirtualAccount::query()
            ->where('status', 'available')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->update(['status' => 'expired']);

        $assignedNow = false;

        $payment = DB::transaction(function () use ($registration, $staff, &$assignedNow): ?Payment {
            $lockedRegistration = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            $lockedRegistration->assertCurrentStage('virtual_account');

            if ($lockedRegistration->data_validation_status !== 'valid') {
                throw ValidationException::withMessages([
                    'data_validation_status' => 'Virtual account hanya dapat diterbitkan setelah data pendaftaran dinyatakan valid.',
                ]);
            }

            $fee = (float) ($lockedRegistration->opening()->value('registration_fee') ?? 0);

            $existingVa = VirtualAccount::query()
                ->where('registration_id', $lockedRegistration->id)
                ->first();

            if ($existingVa) {
                $existingPayment = Payment::query()
                    ->where('virtual_account_id', $existingVa->id)
                    ->first();

                if ($existingPayment) {
                    if ($existingPayment->amount === null) {
                        $existingPayment->update(['amount' => $fee]);
                    }

                    $lockedRegistration->transitionTo('payment', ['status' => 'payment_pending']);

                    return $existingPayment->load(['registration.user', 'registration.unit']);
                }

                $payment = Payment::create([
                    'registration_id' => $lockedRegistration->id,
                    'virtual_account_id' => $existingVa->id,
                    'va_number' => $existingVa->va_number,
                    'amount' => $fee,
                    'status' => 'pending',
                    'va_sent_at' => now(),
                    'va_sent_by' => $staff->id,
                ]);

                $lockedRegistration->transitionTo('payment', ['status' => 'payment_pending']);
                $assignedNow = true;

                return $payment->load(['registration.user', 'registration.unit']);
            }

            $va = VirtualAccount::query()
                ->available()
                ->where('unit_id', $lockedRegistration->unit_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $va) {
                return null;
            }

            $va->update([
                'status' => 'assigned',
                'registration_id' => $lockedRegistration->id,
                'assigned_by' => $staff->id,
                'assigned_at' => now(),
            ]);

            $payment = Payment::create([
                'registration_id' => $lockedRegistration->id,
                'virtual_account_id' => $va->id,
                'va_number' => $va->va_number,
                'amount' => $fee,
                'status' => 'pending',
                'va_sent_at' => now(),
                'va_sent_by' => $staff->id,
            ]);

            $lockedRegistration->transitionTo('payment', [
                'status' => 'payment_pending',
            ]);

            $assignedNow = true;

            return $payment->load(['registration.user', 'registration.unit']);
        });

        if ($payment && $assignedNow) {
            SendVirtualAccountMail::dispatch($payment->id);
            $this->notifications->virtualAccountIssued($payment);
        } elseif (! $payment) {
            $this->notifications->virtualAccountPoolEmpty($registration->fresh());
        }

        return $payment;
    }

    public function assignWaitingRegistrationsForUnit(Unit $unit, User $staff): int
    {
        $assigned = 0;

        $registrations = Registration::query()
            ->where('unit_id', $unit->id)
            ->where('lifecycle_status', 'active')
            ->where('current_stage', 'virtual_account')
            ->where('data_validation_status', 'valid')
            ->orderBy('id')
            ->get();

        foreach ($registrations as $registration) {
            if (! $this->assignAvailableVirtualAccount($registration, $staff)) {
                break;
            }

            $assigned++;
        }

        return $assigned;
    }

    public function issueVirtualAccount(Registration $registration, User $staff, string $vaNumber, ?float $legacyAmount = null): Payment
    {
        $payment = DB::transaction(function () use ($registration, $staff, $vaNumber, $legacyAmount): Payment {
            $lockedRegistration = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            $lockedRegistration->assertCurrentStage('virtual_account');

            if ($lockedRegistration->data_validation_status !== 'valid') {
                throw ValidationException::withMessages([
                    'data_validation_status' => 'Virtual account hanya dapat diterbitkan setelah data pendaftaran dinyatakan valid.',
                ]);
            }

            $openingFee = $lockedRegistration->opening()->value('registration_fee');
            $amount = $openingFee !== null ? (float) $openingFee : (float) ($legacyAmount ?? 0);

            $payment = $lockedRegistration->latestPayment()->first() ?? new Payment([
                'registration_id' => $lockedRegistration->id,
            ]);

            $payment->fill([
                'va_number' => $vaNumber,
                'amount' => $amount,
                'status' => 'pending',
                'va_sent_at' => now(),
                'va_sent_by' => $staff->id,
                'rejection_reason' => null,
            ])->save();

            $lockedRegistration->transitionTo('payment', [
                'status' => 'payment_pending',
            ]);

            return $payment->fresh(['registration.user', 'registration.unit']);
        });

        SendVirtualAccountMail::dispatch($payment->id);
        $this->notifications->virtualAccountIssued($payment);

        return $payment;
    }

    public function markPaymentUploaded(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $registration = Registration::query()->lockForUpdate()->findOrFail($lockedPayment->registration_id);
            $registration->assertCurrentStage('payment');

            if (! $lockedPayment->proof_path || ! $lockedPayment->proof_sha256 || ! $lockedPayment->proof_security_scanned_at) {
                throw ValidationException::withMessages([
                    'proof' => 'Bukti pembayaran belum melewati pemeriksaan keamanan upload.',
                ]);
            }

            $lockedPayment->update([
                'status' => 'paid',
                'payment_date' => now(),
                'proof_uploaded_at' => now(),
                'rejection_reason' => null,
            ]);

            $registration->transitionTo('payment_verification', [
                'status' => 'payment_uploaded',
            ]);
        });

        $this->notifications->paymentProofUploaded($payment->fresh(['registration.unit']));
    }

    public function verifyPayment(Payment $payment, User $staff, bool $approved, ?string $reason = null): void
    {
        DB::transaction(function () use ($payment, $staff, $approved, $reason): void {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $registration = Registration::query()->lockForUpdate()->findOrFail($lockedPayment->registration_id);
            $registration->assertCurrentStage('payment_verification');

            if ($approved) {
                $lockedPayment->update([
                    'status' => 'verified',
                    'verified_by' => $staff->id,
                    'verified_at' => now(),
                    'rejection_reason' => null,
                ]);

                $lockedPayment->virtualAccount?->update(['status' => 'paid']);

                $registration->transitionTo('applicant_card', [
                    'status' => 'payment_verified',
                    'payment_verified_at' => now(),
                ]);

                return;
            }

            $lockedPayment->update([
                'status' => 'rejected',
                'verified_by' => $staff->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $registration->transitionTo('payment', [
                'status' => 'payment_pending',
            ]);
        });

        $this->notifications->paymentVerificationResult($payment->fresh(['registration.user']), $approved, $reason);
    }

    public function issueApplicantCard(Registration $registration, User $staff): void
    {
        DB::transaction(function () use ($registration, $staff): void {
            $lockedRegistration = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            $lockedRegistration->assertCurrentStage('applicant_card');

            $lockedRegistration->transitionTo('documents', [
                'applicant_card_number' => $lockedRegistration->applicant_card_number ?: $lockedRegistration->generateApplicantCardNumber(),
                'applicant_card_issued_by' => $staff->id,
                'applicant_card_issued_at' => now(),
            ]);
        });

        $this->notifications->applicantCardIssued($registration->fresh());
    }

    public function refreshDocumentStage(Registration $registration): bool
    {
        $hasTests = false;

        $complete = DB::transaction(function () use ($registration, &$hasTests): bool {
            $lockedRegistration = Registration::query()
                ->with(['unit', 'opening'])
                ->lockForUpdate()
                ->findOrFail($registration->id);

            $lockedRegistration->assertCurrentStage(['documents', 'document_verification']);

            $types = $lockedRegistration->documents()
                ->where('is_verified', true)
                ->whereNotNull('security_scanned_at')
                ->pluck('type')
                ->unique();

            $complete = collect(self::REQUIRED_DOCUMENTS)
                ->every(fn (string $type): bool => $types->contains($type));

            if (! $complete) {
                $lockedRegistration->transitionTo('document_verification', [
                    'documents_verified_at' => null,
                ]);

                return false;
            }

            $studyProgramId = $lockedRegistration->opening?->study_program_id;

            $tests = $lockedRegistration->unit
                ->admissionTests()
                ->where('is_active', true)
                ->where(function ($query) use ($studyProgramId): void {
                    $query->whereNull('study_program_id');

                    if ($studyProgramId) {
                        $query->orWhere('study_program_id', $studyProgramId);
                    }
                })
                ->get();

            $hasTests = $tests->isNotEmpty();

            foreach ($tests as $test) {
                AdmissionTestResult::firstOrCreate(
                    [
                        'registration_id' => $lockedRegistration->id,
                        'admission_test_id' => $test->id,
                    ],
                    [
                        'status' => 'scheduled',
                        'result' => 'pending',
                    ],
                );
            }

            if ($tests->isEmpty()) {
                Selection::firstOrCreate(
                    ['registration_id' => $lockedRegistration->id],
                    ['decision' => 'pending'],
                );
            }

            $lockedRegistration->transitionTo(
                $tests->isEmpty() ? 'selection' : 'tests',
                [
                    'documents_completed_at' => $lockedRegistration->documents_completed_at ?: now(),
                    'documents_verified_at' => now(),
                ],
            );

            return true;
        });

        if ($complete) {
            $this->notifications->documentsVerified($registration->fresh(), $hasTests);
        }

        return $complete;
    }

    public function recordTestResult(AdmissionTestResult $result, User $staff, array $data): void
    {
        $completedNow = false;
        $registrationId = $result->registration_id;

        DB::transaction(function () use ($result, $staff, $data, &$completedNow): void {
            $lockedResult = AdmissionTestResult::query()->lockForUpdate()->findOrFail($result->id);
            $registration = Registration::query()->lockForUpdate()->findOrFail($lockedResult->registration_id);
            $registration->assertCurrentStage('tests');

            $lockedResult->update($data + [
                'assessed_by' => $staff->id,
                'assessed_at' => now(),
            ]);

            $pending = $registration->testResults()
                ->whereNotIn('status', ['completed', 'exempted', 'absent'])
                ->exists();

            if (! $pending) {
                Selection::firstOrCreate(
                    ['registration_id' => $registration->id],
                    ['decision' => 'pending'],
                );

                $registration->transitionTo('selection');
                $completedNow = true;
            }
        });

        if ($completedNow) {
            $this->notifications->testsCompleted(Registration::query()->findOrFail($registrationId));
        }
    }

    public function decide(Registration $registration, User $staff, string $decision, ?float $score = null, ?string $notes = null): Selection
    {
        if (! in_array($decision, ['accepted', 'rejected', 'waiting_list'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Keputusan seleksi harus Diterima, Ditolak, atau Daftar Tunggu.',
            ]);
        }

        $selection = DB::transaction(function () use ($registration, $staff, $decision, $score, $notes): Selection {
            $lockedRegistration = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            $lockedRegistration->assertCurrentStage('selection');

            $selection = Selection::updateOrCreate(
                ['registration_id' => $lockedRegistration->id],
                [
                    'decision' => $decision,
                    'final_score' => $score,
                    'notes' => $notes,
                    'decided_by' => $staff->id,
                    'decided_at' => now(),
                ],
            );

            Announcement::firstOrCreate(
                ['registration_id' => $lockedRegistration->id],
                [
                    'status' => 'draft',
                    'title' => 'Pengumuman Hasil SPMB',
                ],
            );

            $lockedRegistration->transitionTo('announcement');

            return $selection;
        });

        $this->notifications->selectionDecided($registration->fresh(), $decision);

        return $selection;
    }

    public function publish(Registration $registration, User $staff, ?string $title = null, ?string $message = null): Announcement
    {
        $announcement = DB::transaction(function () use ($registration, $staff, $title, $message): Announcement {
            $lockedRegistration = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            $lockedRegistration->assertCurrentStage('announcement');

            $selection = $lockedRegistration->selection()->first();

            if (! $selection || ! in_array($selection->decision, ['accepted', 'rejected', 'waiting_list'], true)) {
                throw ValidationException::withMessages([
                    'selection' => 'Pengumuman tidak dapat diterbitkan sebelum keputusan seleksi final tersedia.',
                ]);
            }

            $announcement = Announcement::updateOrCreate(
                ['registration_id' => $lockedRegistration->id],
                [
                    'status' => 'published',
                    'title' => $title ?: 'Pengumuman Hasil SPMB',
                    'message' => $message,
                    'published_by' => $staff->id,
                    'published_at' => now(),
                    'email_sent_at' => null,
                ],
            );

            $legacyStatus = match ($selection->decision) {
                'accepted' => 'accepted',
                'rejected' => 'rejected',
                'waiting_list' => 'waiting_list',
            };

            $lockedRegistration->transitionTo('completed', [
                'status' => $legacyStatus,
                'accepted_at' => $legacyStatus === 'accepted' ? now() : $lockedRegistration->accepted_at,
            ]);

            return $announcement->fresh(['registration.user']);
        });

        SendAnnouncementPublishedMail::dispatch($announcement->id);
        $this->notifications->announcementPublished($announcement);

        return $announcement;
    }
}
