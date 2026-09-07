<?php

namespace App\Services;

use App\Jobs\SendAnnouncementPublishedMail;
use App\Jobs\SendVirtualAccountMail;
use App\Models\AdmissionTestResult;
use App\Models\Announcement;
use App\Models\Document;
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

    /** @return list<string> */
    public static function requiredDocuments(Registration $registration): array
    {
        return array_column(array_filter($registration->documentRequirements(), fn (array $requirement): bool => (bool) $requirement['required']), 'key');
    }

    public function rejectDocument(Document $document, User $staff, string $reason): void
    {
        abort_unless($staff->can('verify_document_document'), 403);

        if (blank($reason)) {
            throw ValidationException::withMessages(['rejection_reason' => 'Alasan penolakan wajib diisi.']);
        }

        DB::transaction(function () use ($document, $staff, $reason): void {
            $registration = Registration::query()->lockForUpdate()->findOrFail($document->registration_id);
            abort_if($staff->isTU() && $staff->unit_id !== $registration->unit_id, 403);
            $lockedDocument = $registration->documents()->lockForUpdate()->findOrFail($document->id);
            $lockedDocument->setRelation('registration', $registration);
            $lockedDocument->assertCanBeReviewed();
            $lockedDocument->update([
                'is_verified' => false,
                'verified_at' => null,
                'verified_by' => $staff->id,
                'rejection_reason' => trim($reason),
            ]);
            if (in_array($registration->current_stage, ['documents', 'document_verification'], true)) {
                $registration->transitionTo('documents', [
                    'documents_completed_at' => null,
                    'documents_verified_at' => null,
                ]);
            }
        });

        $registration = $document->registration->fresh();
        if (! in_array($registration->current_stage, ['documents', 'document_verification'], true)) {
            $this->notifications->workflowEvent(
                $registration,
                'document.optional_rejected',
                'Berkas opsional ditolak',
                $document->original_name.' ditolak. Alasan: '.trim($reason),
            );

            return;
        }

        $this->notifications->documentNeedsAttention(
            $document->registration->fresh(),
            str($document->type)->headline()->toString(),
            trim($reason),
        );
    }

    public function validateData(Registration $registration, User $staff, bool $approved, ?string $notes = null): void
    {
        $registration = Registration::query()->findOrFail($registration->id);
        $registration->assertCurrentStage('data_validation');

        $registration->transitionTo(
            $approved ? ($registration->configuration && ! $registration->configuration->payment_enabled ? 'applicant_card' : 'virtual_account') : 'data_validation',
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

        if ($approved && $registration->current_stage === 'virtual_account') {
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
                app(ReceiptService::class)->issue($lockedPayment);

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

            $target = $lockedRegistration->configuration && ! $lockedRegistration->configuration->documents_enabled
                ? (collect($lockedRegistration->configuredTests())->contains('is_required', true) ? 'tests' : 'selection')
                : 'documents';
            $lockedRegistration->transitionTo($target, [
                'applicant_card_number' => $lockedRegistration->applicant_card_number ?: $lockedRegistration->generateApplicantCardNumber(),
                'applicant_card_issued_by' => $staff->id,
                'applicant_card_issued_at' => now(),
            ]);
            if (in_array($target, ['tests', 'selection'], true)) {
                $this->prepareTestsAndSelection($lockedRegistration);
            }
        });

        $fresh = $registration->fresh();
        $this->notifications->applicantCardIssued($fresh);
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

            $complete = $lockedRegistration->documentsComplete(true);

            if (! $complete) {
                $lockedRegistration->transitionTo('document_verification', [
                    'documents_verified_at' => null,
                ]);

                return false;
            }

            if ($lockedRegistration->current_stage === 'documents') {
                $lockedRegistration->transitionTo('document_verification');
            }

            $hasTests = $this->prepareTestsAndSelection($lockedRegistration);

            $lockedRegistration->transitionTo(
                $hasTests ? 'tests' : 'selection',
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

    public function prepareTestsAndSelection(Registration $registration): bool
    {
        $tests = $registration->configuredTests();
        foreach ($tests as $test) {
            AdmissionTestResult::firstOrCreate(['registration_id' => $registration->id, 'admission_test_id' => $test['id']], ['status' => 'scheduled', 'result' => 'pending']);
        }
        $required = collect($tests)->where('is_required', true)->isNotEmpty();
        if (! $required) {
            Selection::firstOrCreate(['registration_id' => $registration->id], ['decision' => 'pending']);
            $this->notifications->workflowEvent($registration, 'selection.ready', 'Pendaftaran siap diseleksi', 'Tahap wajib sebelum seleksi telah terpenuhi.', false, true);
        }

        return $required;
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

            $requiredTestIds = collect($registration->configuredTests())->where('is_required', true)->pluck('id');
            $completedTestIds = $registration->testResults()
                ->whereIn('admission_test_id', $requiredTestIds)
                ->whereIn('status', ['completed', 'exempted', 'absent'])
                ->pluck('admission_test_id');
            $pending = $requiredTestIds->diff($completedTestIds)->isNotEmpty();

            if (! $pending) {
                $scoreTestIds = collect($registration->configuredTests())
                    ->where('is_required', true)
                    ->where('result_type', 'score')
                    ->pluck('id');

                $scores = $registration->testResults()
                    ->whereIn('admission_test_id', $scoreTestIds)
                    ->whereNotNull('score')
                    ->pluck('score')
                    ->map(fn ($score): float => (float) $score);

                $selection = Selection::firstOrCreate(
                    ['registration_id' => $registration->id],
                    ['decision' => 'pending'],
                );

                if ($scores->isNotEmpty()) {
                    $selection->update([
                        'final_score' => round((float) $scores->avg(), 2),
                    ]);
                }

                $registration->transitionTo('selection');
                $completedNow = true;
            }
        });

        if ($completedNow) {
            $this->notifications->testsCompleted(Registration::query()->findOrFail($registrationId));
        }
    }

    public function reviewDecision(Registration $registration, User $staff, string $decision, ?float $score = null, ?string $notes = null): Selection
    {
        abort_if($staff->isTU() && $staff->unit_id !== $registration->unit_id, 403);

        if (! in_array($decision, ['accepted', 'rejected', 'waiting_list'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Keputusan seleksi harus Diterima, Ditolak, atau Daftar Tunggu.',
            ]);
        }

        $selection = DB::transaction(function () use ($registration, $staff, $decision, $score, $notes): Selection {
            $lockedRegistration = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            $lockedRegistration->assertCurrentStage('selection');

            if ($lockedRegistration->selectionMode() === 'manual') {
                throw ValidationException::withMessages([
                    'selection' => 'Versi pendaftaran ini menggunakan penetapan hasil manual tanpa batch.',
                ]);
            }

            $selection = Selection::query()
                ->with('batch')
                ->where('registration_id', $lockedRegistration->id)
                ->lockForUpdate()
                ->first();

            if (! $selection || $selection->batch?->status !== 'ranked') {
                throw ValidationException::withMessages([
                    'selection' => 'Kandidat harus berasal dari batch yang telah diranking untuk menggunakan review keputusan.',
                ]);
            }

            if ($selection->system_recommendation && $selection->system_recommendation !== $decision && blank($notes)) {
                throw ValidationException::withMessages([
                    'notes' => 'Alasan wajib diisi ketika keputusan berbeda dari rekomendasi sistem.',
                ]);
            }

            return Selection::updateOrCreate(
                ['registration_id' => $lockedRegistration->id],
                [
                    'decision' => $decision,
                    'final_score' => $score,
                    'notes' => $notes,
                    'override_reason' => $selection?->system_recommendation && $selection->system_recommendation !== $decision ? $notes : null,
                    'decided_by' => $staff->id,
                    'decided_at' => now(),
                ],
            );
        });

        $this->notifications->selectionDecided($registration->fresh(), $decision);

        return $selection;
    }

    public function decide(Registration $registration, User $staff, string $decision, ?float $score = null, ?string $notes = null): Selection
    {
        abort_if($staff->isTU() && $staff->unit_id !== $registration->unit_id, 403);

        if (! in_array($decision, ['accepted', 'rejected', 'waiting_list'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Keputusan seleksi harus Diterima, Ditolak, atau Daftar Tunggu.',
            ]);
        }

        $selection = DB::transaction(function () use ($registration, $staff, $decision, $score, $notes): Selection {
            $lockedRegistration = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            $lockedRegistration->assertCurrentStage('selection');

            if ($lockedRegistration->usesSelectionBatch()) {
                throw ValidationException::withMessages([
                    'selection' => 'Versi pendaftaran ini mewajibkan Batch Seleksi. Buat ranking lalu finalkan melalui Penetapan Hasil.',
                ]);
            }

            $selection = Selection::query()
                ->with('batch')
                ->where('registration_id', $lockedRegistration->id)
                ->lockForUpdate()
                ->first();

            if ($selection?->selection_batch_id) {
                throw ValidationException::withMessages([
                    'selection' => 'Kandidat sudah masuk Batch Seleksi. Gunakan Review Keputusan dan Finalkan Batch.',
                ]);
            }

            if ($decision === 'accepted') {
                app(AdmissionDecisionService::class)->assertCapacityForAcceptance($lockedRegistration, $selection);
            }
            if ($selection?->system_recommendation && $selection->system_recommendation !== $decision && blank($notes)) {
                throw ValidationException::withMessages([
                    'notes' => 'Alasan wajib diisi ketika keputusan final berbeda dari rekomendasi sistem.',
                ]);
            }

            $selection = Selection::updateOrCreate(
                ['registration_id' => $lockedRegistration->id],
                [
                    'decision' => $decision,
                    'final_score' => $score,
                    'notes' => $notes,
                    'override_reason' => $selection?->system_recommendation && $selection->system_recommendation !== $decision ? $notes : null,
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

            if (! $lockedRegistration->postAnnouncementEnabled()) {
                $attributes = ['status' => $selection->decision];

                if ($selection->decision === 'accepted') {
                    $attributes['accepted_at'] = $lockedRegistration->accepted_at ?: now();
                }

                $lockedRegistration->transitionTo('completed', $attributes);
            } elseif ($selection->decision === 'rejected') {
                $lockedRegistration->transitionTo('completed', ['status' => 'rejected']);
            }

            return $announcement->fresh(['registration.user']);
        });

        $publishedRegistration = $announcement->registration->fresh(['configuration']);
        $decision = $publishedRegistration->selection()->value('decision');

        if ($publishedRegistration->postAnnouncementEnabled() && $decision === 'accepted') {
            app(AdmissionDecisionService::class)->publishAccepted($publishedRegistration);
        } elseif ($publishedRegistration->postAnnouncementEnabled() && $decision === 'waiting_list') {
            app(AdmissionDecisionService::class)->publishWaitingList($publishedRegistration);
        }

        SendAnnouncementPublishedMail::dispatch($announcement->id);
        $this->notifications->announcementPublished($announcement);

        return $announcement;
    }
}
