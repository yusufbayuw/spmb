<?php

namespace App\Services;

use App\Mail\AnnouncementPublishedMail;
use App\Mail\VirtualAccountMail;
use App\Models\AdmissionTestResult;
use App\Models\Announcement;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Selection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RegistrationWorkflowService
{
    public const REQUIRED_DOCUMENTS = ['report_card', 'family_card', 'birth_certificate', 'photo'];

    public function validateData(Registration $registration, User $staff, bool $approved, ?string $notes = null): void
    {
        $registration->update([
            'data_validation_status' => $approved ? 'valid' : 'revision',
            'data_validation_notes' => $notes,
            'data_validated_by' => $staff->id,
            'data_validated_at' => now(),
            'verified_at' => $approved ? now() : null,
            'status' => $approved ? 'verified' : 'submitted',
            'current_stage' => $approved ? 'virtual_account' : 'data_validation',
        ]);
    }

    public function issueVirtualAccount(Registration $registration, User $staff, string $vaNumber, float $amount): Payment
    {
        $payment = DB::transaction(function () use ($registration, $staff, $vaNumber, $amount): Payment {
            $payment = $registration->latestPayment()->first() ?? new Payment([
                'registration_id' => $registration->id,
            ]);

            $payment->fill([
                'va_number' => $vaNumber,
                'amount' => $amount,
                'status' => 'pending',
                'va_sent_at' => now(),
                'va_sent_by' => $staff->id,
                'rejection_reason' => null,
            ])->save();

            $registration->update([
                'status' => 'payment_pending',
                'current_stage' => 'payment',
            ]);

            return $payment->fresh(['registration.user', 'registration.unit']);
        });

        Mail::to($registration->user->email)->send(new VirtualAccountMail($payment));

        return $payment;
    }

    public function markPaymentUploaded(Payment $payment): void
    {
        $payment->update([
            'status' => 'paid',
            'payment_date' => now(),
            'proof_uploaded_at' => now(),
            'rejection_reason' => null,
        ]);

        $payment->registration->update([
            'status' => 'payment_uploaded',
            'current_stage' => 'payment_verification',
        ]);
    }

    public function verifyPayment(Payment $payment, User $staff, bool $approved, ?string $reason = null): void
    {
        if ($approved) {
            $payment->update([
                'status' => 'verified',
                'verified_by' => $staff->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);

            $payment->registration->update([
                'status' => 'payment_verified',
                'payment_verified_at' => now(),
                'current_stage' => 'applicant_card',
            ]);

            return;
        }

        $payment->update([
            'status' => 'rejected',
            'verified_by' => $staff->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $payment->registration->update([
            'status' => 'payment_pending',
            'current_stage' => 'payment',
        ]);
    }

    public function issueApplicantCard(Registration $registration, User $staff): void
    {
        $registration->update([
            'applicant_card_number' => $registration->applicant_card_number ?: $registration->generateApplicantCardNumber(),
            'applicant_card_issued_by' => $staff->id,
            'applicant_card_issued_at' => now(),
            'current_stage' => 'documents',
        ]);
    }

    public function refreshDocumentStage(Registration $registration): bool
    {
        $types = $registration->documents()
            ->where('is_verified', true)
            ->pluck('type')
            ->unique();

        $complete = collect(self::REQUIRED_DOCUMENTS)
            ->every(fn (string $type): bool => $types->contains($type));

        if (! $complete) {
            $registration->update([
                'current_stage' => 'document_verification',
                'documents_verified_at' => null,
            ]);

            return false;
        }

        DB::transaction(function () use ($registration): void {
            $registration->update([
                'documents_completed_at' => $registration->documents_completed_at ?: now(),
                'documents_verified_at' => now(),
            ]);

            $tests = $registration->unit
                ->admissionTests()
                ->where('is_active', true)
                ->get();

            foreach ($tests as $test) {
                AdmissionTestResult::firstOrCreate(
                    [
                        'registration_id' => $registration->id,
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
                    ['registration_id' => $registration->id],
                    ['decision' => 'pending'],
                );
            }

            $registration->update([
                'current_stage' => $tests->isEmpty() ? 'selection' : 'tests',
            ]);
        });

        return true;
    }

    public function recordTestResult(AdmissionTestResult $result, User $staff, array $data): void
    {
        $result->update($data + [
            'assessed_by' => $staff->id,
            'assessed_at' => now(),
        ]);

        $registration = $result->registration;
        $pending = $registration->testResults()
            ->whereNotIn('status', ['completed', 'exempted', 'absent'])
            ->exists();

        if (! $pending) {
            Selection::firstOrCreate(
                ['registration_id' => $registration->id],
                ['decision' => 'pending'],
            );

            $registration->update(['current_stage' => 'selection']);
        }
    }

    public function decide(Registration $registration, User $staff, string $decision, ?float $score = null, ?string $notes = null): Selection
    {
        $selection = Selection::updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'decision' => $decision,
                'final_score' => $score,
                'notes' => $notes,
                'decided_by' => $staff->id,
                'decided_at' => now(),
            ],
        );

        Announcement::firstOrCreate(
            ['registration_id' => $registration->id],
            [
                'status' => 'draft',
                'title' => 'Pengumuman Hasil SPMB',
            ],
        );

        $registration->update(['current_stage' => 'announcement']);

        return $selection;
    }

    public function publish(Registration $registration, User $staff, ?string $title = null, ?string $message = null): Announcement
    {
        $selection = $registration->selection;

        $announcement = Announcement::updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'status' => 'published',
                'title' => $title ?: 'Pengumuman Hasil SPMB',
                'message' => $message,
                'published_by' => $staff->id,
                'published_at' => now(),
            ],
        );

        $legacyStatus = match ($selection?->decision) {
            'accepted' => 'accepted',
            'rejected' => 'rejected',
            'waiting_list' => 'waiting_list',
            default => $registration->status,
        };

        $registration->update([
            'status' => $legacyStatus,
            'accepted_at' => $legacyStatus === 'accepted' ? now() : $registration->accepted_at,
            'current_stage' => 'completed',
        ]);

        Mail::to($registration->user->email)
            ->send(new AnnouncementPublishedMail($announcement));

        $announcement->update(['email_sent_at' => now()]);

        return $announcement;
    }
}
