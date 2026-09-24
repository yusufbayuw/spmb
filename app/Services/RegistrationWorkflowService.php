<?php

namespace App\Services;

use App\Jobs\SendAnnouncementPublishedMail;
use App\Jobs\SendVirtualAccountMail;
use App\Models\AdmissionOffer;
use App\Models\AdmissionTestResult;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Selection;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Support\Collection;
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

        $paymentEnabled = (bool) ($registration->configuration?->payment_enabled ?? true);
        $targetStage = $approved
            ? ($paymentEnabled
                ? 'virtual_account'
                : ($registration->usesConfigurableWorkflow()
                    ? ($registration->nextEnabledStage('data_validation') ?? 'selection')
                    : 'applicant_card'))
            : 'data_validation';

        $registration->transitionTo(
            $targetStage,
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

        if (! $approved) {
            return;
        }

        if ($registration->current_stage === 'virtual_account') {
            $this->assignAvailableVirtualAccount($registration, $staff);

            return;
        }

        DB::transaction(function () use ($registration): void {
            $lockedRegistration = Registration::query()
                ->lockForUpdate()
                ->findOrFail($registration->id);

            app(RegistrationNumberService::class)->assign($lockedRegistration);
        });

        $registration->refresh();

        if ($registration->current_stage === 'applicant_card') {
            $this->issueApplicantCard($registration, $staff);
        } elseif (in_array($registration->current_stage, ['tests', 'selection'], true)) {
            $this->prepareTestsAndSelection($registration);
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

            $studyProgramId = $lockedRegistration->opening()->value('study_program_id');
            $va = null;

            if ($studyProgramId) {
                $va = VirtualAccount::query()
                    ->available()
                    ->where('unit_id', $lockedRegistration->unit_id)
                    ->where('study_program_id', $studyProgramId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $va) {
                $va = VirtualAccount::query()
                    ->available()
                    ->where('unit_id', $lockedRegistration->unit_id)
                    ->whereNull('study_program_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
            }

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
                continue;
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
        $cardIssued = false;

        DB::transaction(function () use ($payment, $staff, $approved, $reason, &$cardIssued): void {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $registration = Registration::query()
                ->with(['configuration', 'opening', 'unit'])
                ->lockForUpdate()
                ->findOrFail($lockedPayment->registration_id);
            $registration->assertCurrentStage('payment_verification');

            if ($approved) {
                $lockedPayment->update([
                    'status' => 'verified',
                    'verified_by' => $staff->id,
                    'verified_at' => now(),
                    'rejection_reason' => null,
                ]);

                $lockedPayment->virtualAccount?->update(['status' => 'paid']);

                $targetStage = $registration->usesConfigurableWorkflow()
                    ? ($registration->nextEnabledStage('payment_verification') ?? 'selection')
                    : 'applicant_card';

                $registration->transitionTo($targetStage, [
                    'status' => 'payment_verified',
                    'payment_verified_at' => now(),
                ]);

                $registration->loadMissing(['configuration', 'opening', 'unit']);
                app(RegistrationNumberService::class)->assign($registration);

                // Receipt snapshots must already contain the official registration number.
                app(ReceiptService::class)->issue($lockedPayment);

                if ($targetStage === 'applicant_card') {
                    $this->advanceFromApplicantCard($registration, $staff);
                    $cardIssued = true;
                } elseif (in_array($targetStage, ['tests', 'selection'], true)) {
                    $this->prepareTestsAndSelection($registration);
                }

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

        $fresh = $payment->fresh(['registration.user'])->registration;
        $this->notifications->paymentVerificationResult($payment->fresh(['registration.user']), $approved, $reason);

        if ($approved && $cardIssued && $fresh) {
            $this->notifications->applicantCardIssued($fresh);
        }
    }

    public function issueApplicantCard(Registration $registration, User $staff): void
    {
        DB::transaction(function () use ($registration, $staff): void {
            $lockedRegistration = Registration::query()
                ->with(['configuration', 'opening', 'unit'])
                ->lockForUpdate()
                ->findOrFail($registration->id);
            $lockedRegistration->assertCurrentStage('applicant_card');

            if ($lockedRegistration->configuration?->payment_enabled && ! $lockedRegistration->payment_verified_at) {
                throw ValidationException::withMessages([
                    'payment' => 'Kartu pendaftar hanya dapat diterbitkan setelah pembayaran VA diverifikasi.',
                ]);
            }

            app(RegistrationNumberService::class)->assign($lockedRegistration);
            $this->advanceFromApplicantCard($lockedRegistration, $staff);
        });

        $fresh = $registration->fresh();
        $this->notifications->applicantCardIssued($fresh);
    }

    private function advanceFromApplicantCard(Registration $registration, User $staff): void
    {
        $registration->assertCurrentStage('applicant_card');

        $target = $registration->usesConfigurableWorkflow()
            ? ($registration->nextEnabledStage('applicant_card') ?? 'selection')
            : ($registration->configuration && ! $registration->configuration->documents_enabled
                ? (collect($registration->configuredTests())->contains('is_required', true) ? 'tests' : 'selection')
                : 'documents');

        $registration->transitionTo($target, [
            'applicant_card_number' => $registration->applicant_card_number ?: $registration->generateApplicantCardNumber(),
            'applicant_card_issued_by' => $staff->id,
            'applicant_card_issued_at' => now(),
        ]);

        if (in_array($target, ['tests', 'selection'], true)) {
            $this->prepareTestsAndSelection($registration);
        }
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

            if ($lockedRegistration->usesConfigurableWorkflow()) {
                $targetStage = $lockedRegistration->nextEnabledStage('document_verification') ?? 'selection';

                if (in_array($targetStage, ['tests', 'selection'], true)) {
                    $hasTests = $this->prepareTestsAndSelection($lockedRegistration);
                }
            } else {
                $hasTests = $this->prepareTestsAndSelection($lockedRegistration);
                $targetStage = $hasTests ? 'tests' : 'selection';
            }

            $lockedRegistration->transitionTo(
                $targetStage,
                [
                    'documents_completed_at' => $lockedRegistration->documents_completed_at ?: now(),
                    'documents_verified_at' => now(),
                ],
            );

            return true;
        });

        if ($complete) {
            $fresh = $registration->fresh();
            $this->notifications->documentsVerified($fresh, $hasTests, $fresh->current_stage);
        }

        return $complete;
    }

    public function prepareTestsAndSelection(Registration $registration): bool
    {
        $tests = $registration->configuredTests();
        foreach ($tests as $test) {
            AdmissionTestResult::firstOrCreate(['registration_id' => $registration->id, 'admission_test_id' => $test['id']], ['status' => 'unbooked', 'result' => 'pending']);
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
                    'waitlist_rank' => $this->waitlistRankFor($lockedRegistration, $selection, $decision),
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
                    'waitlist_rank' => $this->waitlistRankFor($lockedRegistration, $selection ?? new Selection(['registration_id' => $lockedRegistration->id]), $decision),
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

    public function correctDraftDecision(
        Selection $selection,
        User $staff,
        string $decision,
        ?float $score,
        string $reason,
    ): Selection {
        $this->assertSelectionDecision($decision);

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan koreksi wajib diisi.',
            ]);
        }

        $corrected = DB::transaction(function () use ($selection, $staff, $decision, $score, $reason): Selection {
            $lockedSelection = Selection::query()
                ->lockForUpdate()
                ->findOrFail($selection->id);
            $registration = Registration::query()
                ->lockForUpdate()
                ->findOrFail($lockedSelection->registration_id);

            $this->assertUnitAccess($registration, $staff);
            $registration->assertCurrentStage('announcement');

            $announcement = Announcement::query()
                ->where('registration_id', $registration->id)
                ->lockForUpdate()
                ->first();

            if (! $announcement || $announcement->status !== 'draft') {
                throw ValidationException::withMessages([
                    'announcement' => 'Keputusan hanya dapat dikoreksi dari Penetapan Hasil selama pengumuman masih berupa draft.',
                ]);
            }

            $oldDecision = $lockedSelection->decision;

            if ($decision === 'accepted' && $oldDecision !== 'accepted') {
                app(AdmissionDecisionService::class)->assertCapacityForAcceptance($registration, $lockedSelection);
            }

            $lockedSelection->update([
                'decision' => $decision,
                'final_score' => $score ?? $lockedSelection->final_score,
                'waitlist_rank' => $this->waitlistRankFor($registration, $lockedSelection, $decision),
                'override_reason' => trim($reason),
                'decided_by' => $staff->id,
                'decided_at' => now(),
            ]);

            app(AuditTrail::class)->record(
                'selection.decision_corrected',
                $lockedSelection,
                oldValues: [
                    'decision' => $oldDecision,
                    'final_score' => $selection->final_score,
                ],
                newValues: [
                    'decision' => $decision,
                    'final_score' => $lockedSelection->final_score,
                ],
                metadata: ['reason' => trim($reason), 'published' => false],
                actor: $staff,
                unitId: $registration->unit_id,
                registrationId: $registration->id,
                description: 'Keputusan seleksi dikoreksi sebelum publikasi',
            );

            return $lockedSelection->fresh();
        }, 5);

        $this->notifications->selectionDecided($corrected->registration->fresh(), $decision);

        return $corrected;
    }

    /**
     * @param Collection<int, Selection> $selections
     */
    public function bulkSetDecisions(Collection $selections, User $staff, string $decision, string $reason): int
    {
        if (! in_array($decision, ['accepted', 'rejected', 'waiting_list', 'system'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Pilihan keputusan massal tidak valid.',
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Catatan / alasan tindakan massal wajib diisi.',
            ]);
        }

        $ids = $selections->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'selection' => 'Pilih minimal satu peserta.',
            ]);
        }

        $changedRegistrationIds = [];

        DB::transaction(function () use ($ids, $staff, $decision, $reason, &$changedRegistrationIds): void {
            $lockedSelections = Selection::query()
                ->with('batch')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedSelections->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'selection' => 'Sebagian data seleksi sudah berubah. Muat ulang tabel lalu pilih kembali.',
                ]);
            }

            foreach ($lockedSelections as $lockedSelection) {
                $registration = Registration::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedSelection->registration_id);

                $this->assertUnitAccess($registration, $staff);

                $targetDecision = $decision === 'system'
                    ? $lockedSelection->system_recommendation
                    : $decision;

                if (! in_array($targetDecision, ['accepted', 'rejected', 'waiting_list'], true)) {
                    throw ValidationException::withMessages([
                        'decision' => 'Sebagian peserta tidak memiliki rekomendasi sistem yang dapat diterapkan.',
                    ]);
                }

                $oldDecision = $lockedSelection->decision;

                if ($registration->current_stage === 'selection') {
                    if ($lockedSelection->selection_batch_id) {
                        if ($lockedSelection->batch?->status !== 'ranked') {
                            throw ValidationException::withMessages([
                                'selection' => 'Keputusan peserta dalam batch hanya dapat diubah setelah batch selesai diranking.',
                            ]);
                        }

                        $lockedSelection->update([
                            'decision' => $targetDecision,
                            'waitlist_rank' => $this->waitlistRankFor($registration, $lockedSelection, $targetDecision),
                            'override_reason' => $lockedSelection->system_recommendation !== $targetDecision ? trim($reason) : null,
                            'notes' => trim($reason),
                            'decided_by' => $staff->id,
                            'decided_at' => now(),
                        ]);
                    } else {
                        if (! $registration->allowsManualSelection()) {
                            throw ValidationException::withMessages([
                                'selection' => 'Sebagian peserta wajib diproses melalui Batch Seleksi.',
                            ]);
                        }

                        if ($targetDecision === 'accepted' && $oldDecision !== 'accepted') {
                            app(AdmissionDecisionService::class)->assertCapacityForAcceptance($registration, $lockedSelection);
                        }

                        $lockedSelection->update([
                            'decision' => $targetDecision,
                            'waitlist_rank' => $this->waitlistRankFor($registration, $lockedSelection, $targetDecision),
                            'override_reason' => $lockedSelection->system_recommendation && $lockedSelection->system_recommendation !== $targetDecision ? trim($reason) : null,
                            'notes' => trim($reason),
                            'decided_by' => $staff->id,
                            'decided_at' => now(),
                        ]);

                        Announcement::firstOrCreate(
                            ['registration_id' => $registration->id],
                            ['status' => 'draft', 'title' => 'Pengumuman Hasil SPMB'],
                        );

                        $registration->transitionTo('announcement');
                    }
                } elseif ($registration->current_stage === 'announcement') {
                    $announcement = Announcement::query()
                        ->where('registration_id', $registration->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $announcement || $announcement->status !== 'draft') {
                        throw ValidationException::withMessages([
                            'selection' => 'Tindakan massal hanya dapat mengoreksi hasil yang pengumumannya masih draft.',
                        ]);
                    }

                    if ($targetDecision === 'accepted' && $oldDecision !== 'accepted') {
                        app(AdmissionDecisionService::class)->assertCapacityForAcceptance($registration, $lockedSelection);
                    }

                    $lockedSelection->update([
                        'decision' => $targetDecision,
                        'waitlist_rank' => $this->waitlistRankFor($registration, $lockedSelection, $targetDecision),
                        'override_reason' => trim($reason),
                        'notes' => trim($reason),
                        'decided_by' => $staff->id,
                        'decided_at' => now(),
                    ]);
                } else {
                    throw ValidationException::withMessages([
                        'selection' => 'Sebagian peserta sudah melewati tahap yang aman untuk koreksi massal.',
                    ]);
                }

                app(AuditTrail::class)->record(
                    'selection.bulk_decision_set',
                    $lockedSelection,
                    oldValues: ['decision' => $oldDecision],
                    newValues: ['decision' => $targetDecision],
                    metadata: ['reason' => trim($reason)],
                    actor: $staff,
                    unitId: $registration->unit_id,
                    registrationId: $registration->id,
                    description: 'Keputusan seleksi diperbarui melalui tindakan massal',
                );

                $changedRegistrationIds[] = $registration->id;
            }
        }, 5);

        foreach (array_unique($changedRegistrationIds) as $registrationId) {
            $registration = Registration::query()->find($registrationId);

            if ($registration) {
                $this->notifications->selectionDecided(
                    $registration,
                    (string) $registration->selection()->value('decision'),
                );
            }
        }

        return count(array_unique($changedRegistrationIds));
    }

    public function canCorrectPublished(Announcement $announcement): bool
    {
        if ($announcement->status !== 'published') {
            return false;
        }

        $registration = $announcement->registration;

        if (! $registration || ! $registration->isOperational()) {
            return false;
        }

        if (in_array($registration->current_stage, ['re_registration', 'enrollment'], true)
            || filled($registration->enrolled_at)
            || $registration->reRegistrationItems()->exists()) {
            return false;
        }

        $offer = AdmissionOffer::query()
            ->where('registration_id', $registration->id)
            ->first();

        return ! $offer || $offer->status === 'offered';
    }

    public function correctPublishedDecision(
        Announcement $announcement,
        User $staff,
        string $decision,
        string $reason,
    ): Selection {
        $this->assertSelectionDecision($decision);

        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan koreksi hasil terpublikasi wajib diisi.',
            ]);
        }

        $offerToPromoteFrom = null;

        $selection = DB::transaction(function () use ($announcement, $staff, $decision, $reason, &$offerToPromoteFrom): Selection {
            $lockedAnnouncement = Announcement::query()
                ->lockForUpdate()
                ->findOrFail($announcement->id);

            if ($lockedAnnouncement->status !== 'published') {
                throw ValidationException::withMessages([
                    'announcement' => 'Pengumuman belum dipublikasikan.',
                ]);
            }

            $registration = Registration::query()
                ->with('configuration')
                ->lockForUpdate()
                ->findOrFail($lockedAnnouncement->registration_id);

            $this->assertUnitAccess($registration, $staff);

            if (! $this->canCorrectPublished($lockedAnnouncement->setRelation('registration', $registration))) {
                throw ValidationException::withMessages([
                    'announcement' => 'Hasil tidak dapat dikoreksi langsung karena peserta sudah memasuki proses lanjutan. Gunakan koreksi administratif.',
                ]);
            }

            $lockedSelection = Selection::query()
                ->where('registration_id', $registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldDecision = $lockedSelection->decision;

            if ($oldDecision === $decision) {
                throw ValidationException::withMessages([
                    'decision' => 'Keputusan baru sama dengan keputusan yang sudah dipublikasikan.',
                ]);
            }

            $offer = AdmissionOffer::query()
                ->where('registration_id', $registration->id)
                ->lockForUpdate()
                ->first();

            if ($decision === 'accepted' && $oldDecision !== 'accepted') {
                if ($offer) {
                    throw ValidationException::withMessages([
                        'decision' => 'Peserta memiliki riwayat penawaran penerimaan. Koreksi menjadi Diterima harus dilakukan melalui koreksi administratif.',
                    ]);
                }

                app(AdmissionDecisionService::class)->assertCapacityForAcceptance($registration, $lockedSelection);
            }

            if ($offer && $offer->status === 'offered' && $decision !== 'accepted') {
                $offer->update([
                    'status' => 'expired',
                    'expired_at' => now(),
                ]);
                $offerToPromoteFrom = $offer->fresh('quota');

                app(AuditTrail::class)->record(
                    'admission.offer_cancelled_by_result_correction',
                    $offer,
                    oldValues: ['status' => 'offered'],
                    newValues: ['status' => 'expired'],
                    metadata: ['reason' => trim($reason)],
                    actor: $staff,
                    unitId: $registration->unit_id,
                    registrationId: $registration->id,
                    description: 'Penawaran penerimaan dibatalkan karena koreksi hasil',
                );
            }

            $oldStage = $registration->current_stage;
            $oldStatus = $registration->status;

            $lockedSelection->update([
                'decision' => $decision,
                'waitlist_rank' => $this->waitlistRankFor($registration, $lockedSelection, $decision),
                'override_reason' => trim($reason),
                'notes' => trim($reason),
                'decided_by' => $staff->id,
                'decided_at' => now(),
            ]);

            $lockedAnnouncement->update([
                'published_by' => $staff->id,
                'published_at' => now(),
                'email_sent_at' => null,
            ]);

            if (! $registration->postAnnouncementEnabled()) {
                $registration->forceFill([
                    'current_stage' => 'completed',
                    'status' => $decision,
                    'accepted_at' => $decision === 'accepted' ? ($registration->accepted_at ?: now()) : null,
                ])->save();
            } elseif ($decision === 'accepted') {
                $registration->forceFill([
                    'current_stage' => 'announcement',
                    'status' => 'accepted',
                    'accepted_at' => $registration->accepted_at ?: now(),
                ])->save();
            } elseif ($decision === 'waiting_list') {
                $registration->forceFill([
                    'current_stage' => 'waiting_list',
                    'status' => 'waiting_list',
                    'accepted_at' => null,
                ])->save();
            } else {
                $registration->forceFill([
                    'current_stage' => 'completed',
                    'status' => 'rejected',
                    'accepted_at' => null,
                ])->save();
            }

            app(AuditTrail::class)->record(
                'selection.published_decision_corrected',
                $lockedSelection,
                oldValues: ['decision' => $oldDecision],
                newValues: ['decision' => $decision],
                metadata: ['reason' => trim($reason)],
                actor: $staff,
                unitId: $registration->unit_id,
                registrationId: $registration->id,
                description: 'Hasil seleksi yang sudah dipublikasikan dikoreksi',
            );

            app(AuditTrail::class)->record(
                'registration.stage_corrected_after_publication',
                $registration,
                oldValues: ['current_stage' => $oldStage, 'status' => $oldStatus],
                newValues: ['current_stage' => $registration->current_stage, 'status' => $registration->status],
                metadata: ['reason' => trim($reason), 'decision' => $decision],
                actor: $staff,
                unitId: $registration->unit_id,
                registrationId: $registration->id,
                description: 'Tahap pendaftaran diselaraskan setelah koreksi hasil terpublikasi',
            );

            return $lockedSelection->fresh();
        }, 5);

        $freshRegistration = $selection->registration->fresh(['configuration']);

        if ($freshRegistration->postAnnouncementEnabled() && $decision === 'accepted') {
            app(AdmissionDecisionService::class)->publishAccepted($freshRegistration);
        }

        if ($offerToPromoteFrom?->quota && $decision !== 'accepted') {
            app(AdmissionDecisionService::class)->promoteForQuota($offerToPromoteFrom->quota);
        }

        $freshAnnouncement = $announcement->fresh(['registration.user']);
        SendAnnouncementPublishedMail::dispatch($freshAnnouncement->id);

        $this->notifications->workflowEvent(
            $freshRegistration->fresh(),
            'selection.corrected',
            'Hasil seleksi diperbarui',
            'Hasil seleksi Anda telah dikoreksi oleh petugas. Silakan lihat pengumuman terbaru di portal.',
            true,
            true,
        );

        return $selection->fresh();
    }

    /**
     * @param Collection<int, Announcement> $announcements
     */
    public function bulkPublishAnnouncements(Collection $announcements, User $staff): int
    {
        $ids = $announcements->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'announcement' => 'Pilih minimal satu pengumuman.',
            ]);
        }

        $records = Announcement::query()
            ->with(['registration.selection'])
            ->whereIn('id', $ids)
            ->get();

        if ($records->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'announcement' => 'Sebagian pengumuman sudah berubah. Muat ulang tabel lalu pilih kembali.',
            ]);
        }

        foreach ($records as $record) {
            $registration = $record->registration;
            $this->assertUnitAccess($registration, $staff);

            if ($record->status !== 'draft' || $registration->current_stage !== 'announcement') {
                throw ValidationException::withMessages([
                    'announcement' => 'Semua pengumuman terpilih harus masih berupa draft pada tahap Pengumuman.',
                ]);
            }

            if (! in_array($registration->selection?->decision, ['accepted', 'rejected', 'waiting_list'], true)) {
                throw ValidationException::withMessages([
                    'selection' => 'Semua peserta terpilih harus memiliki keputusan final yang valid.',
                ]);
            }
        }

        foreach ($records as $record) {
            $this->publish(
                $record->registration,
                $staff,
                $record->title,
                $record->message,
            );
        }

        return $records->count();
    }

    private function waitlistRankFor(Registration $registration, Selection $selection, string $decision): ?int
    {
        if ($decision !== 'waiting_list') {
            return null;
        }

        if ($selection->waitlist_rank) {
            return (int) $selection->waitlist_rank;
        }

        $maxRank = Selection::query()
            ->where('id', '!=', $selection->id)
            ->where('decision', 'waiting_list')
            ->whereHas('registration', function ($query) use ($registration): void {
                $query->where('registration_opening_id', $registration->registration_opening_id)
                    ->when(
                        $registration->registration_pathway_id,
                        fn ($scope) => $scope->where('registration_pathway_id', $registration->registration_pathway_id),
                        fn ($scope) => $scope->whereNull('registration_pathway_id'),
                    );
            })
            ->max('waitlist_rank');

        return ((int) $maxRank) + 1;
    }

    private function assertSelectionDecision(string $decision): void
    {
        if (! in_array($decision, ['accepted', 'rejected', 'waiting_list'], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Keputusan seleksi harus Diterima, Ditolak, atau Daftar Tunggu.',
            ]);
        }
    }

    private function assertUnitAccess(Registration $registration, User $staff): void
    {
        abort_if($staff->isTU() && $staff->unit_id !== $registration->unit_id, 403);
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
