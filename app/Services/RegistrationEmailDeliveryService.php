<?php

namespace App\Services;

use App\Jobs\SendAnnouncementPublishedMail;
use App\Jobs\SendApplicantVerificationMail;
use App\Jobs\SendVirtualAccountMail;
use App\Models\Announcement;
use App\Models\MailDeliveryAttempt;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationEmailDeliveryService
{
    private const COOLDOWN_MINUTES = 2;
    private const QUEUED_PROTECTION_MINUTES = 15;
    private const DAILY_LIMIT = 5;

    public function canManage(User $actor, Registration $registration): bool
    {
        return (bool) $actor->is_active
            && ($actor->isAdmin() || ($actor->isAdminUnit()
                && $actor->unit_id !== null
                && (int) $actor->unit_id === (int) $registration->unit_id))
            && $registration->isOperational();
    }

    /** @return array<string, string> */
    public function availableTypes(Registration $registration): array
    {
        $registration->loadMissing(['user', 'latestPayment', 'announcement']);

        $options = [];

        if ($registration->user?->is_active && ! $registration->user->hasVerifiedEmail()) {
            $options['verification'] = MailDeliveryAttempt::TYPES['verification'];
        }

        $payment = $registration->latestPayment;
        if ($registration->current_stage === 'payment'
            && $payment
            && filled($payment->va_number)
            && in_array($payment->status, ['pending', 'rejected'], true)) {
            $options['virtual_account'] = MailDeliveryAttempt::TYPES['virtual_account'];
        }

        if ($registration->announcement?->status === 'published') {
            $options['announcement'] = MailDeliveryAttempt::TYPES['announcement'];
        }

        return $options;
    }

    public function resend(Registration $registration, User $actor, string $type, string $reason): MailDeliveryAttempt
    {
        if (! $this->canManage($actor, $registration)) {
            abort(403);
        }

        if (blank(trim($reason)) || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pengiriman ulang wajib diisi (maksimal 500 karakter).',
            ]);
        }

        // Serializing against a parent registration prevents simultaneous staff
        // requests from passing the same cooldown/quota checks.
        $attempt = DB::transaction(function () use ($registration, $actor, $type, $reason): MailDeliveryAttempt {
            $locked = Registration::query()
                ->with(['user', 'unit', 'latestPayment', 'announcement'])
                ->whereKey($registration->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->canManage($actor->fresh(), $locked)) {
                abort(403);
            }

            $types = $this->availableTypes($locked);
            if (! array_key_exists($type, $types)) {
                throw ValidationException::withMessages([
                    'type' => 'Email ini tidak tersedia untuk kondisi pendaftaran saat ini.',
                ]);
            }

            $latest = MailDeliveryAttempt::query()
                ->where('registration_id', $locked->id)
                ->where('type', $type)
                ->latest('id')
                ->first();

            if ($latest && $latest->status === 'queued'
                && $latest->created_at->greaterThan(now()->subMinutes(self::QUEUED_PROTECTION_MINUTES))) {
                throw ValidationException::withMessages([
                    'type' => 'Email sebelumnya masih dalam antrean. Periksa layanan antrean sebelum mengirim ulang.',
                ]);
            }

            if ($latest && $latest->origin === 'manual'
                && $latest->created_at->greaterThan(now()->subMinutes(self::COOLDOWN_MINUTES))) {
                throw ValidationException::withMessages([
                    'type' => 'Pengiriman ulang dibatasi satu kali setiap dua menit per jenis email.',
                ]);
            }

            if (MailDeliveryAttempt::query()
                ->where('registration_id', $locked->id)
                ->where('type', $type)
                ->where('origin', 'manual')
                ->where('created_at', '>=', now()->subDay())
                ->count() >= self::DAILY_LIMIT) {
                throw ValidationException::withMessages([
                    'type' => 'Batas lima pengiriman ulang per jenis email dalam 24 jam telah tercapai.',
                ]);
            }

            $sourceId = match ($type) {
                'verification' => $locked->user_id,
                'virtual_account' => $locked->latestPayment->id,
                'announcement' => $locked->announcement->id,
            };

            $attempt = MailDeliveryAttempt::query()->create([
                'unit_id' => $locked->unit_id,
                'registration_id' => $locked->id,
                'requested_by' => $actor->id,
                'type' => $type,
                'origin' => 'manual',
                'status' => 'queued',
                'source_id' => $sourceId,
                'recipient_email' => $locked->user->email,
                'reason' => trim($reason),
            ]);

            app(AuditTrail::class)->record(
                'mail.resend_requested',
                $locked,
                metadata: [
                    'mail_type' => $type,
                    'delivery_attempt_id' => $attempt->id,
                    'recipient_email' => $attempt->recipient_email,
                ],
                actor: $actor,
                description: 'Admin Unit meminta pengiriman ulang email: '.$types[$type],
            );

            return $attempt;
        }, 5);

        try {
            match ($type) {
                'verification' => SendApplicantVerificationMail::dispatch($attempt->source_id, $attempt->id),
                'virtual_account' => SendVirtualAccountMail::dispatch($attempt->source_id, $attempt->id),
                'announcement' => SendAnnouncementPublishedMail::dispatch($attempt->source_id, $attempt->id),
            };
        } catch (Throwable $exception) {
            $this->markFailed($attempt->id, $exception->getMessage());
            throw $exception;
        }

        return $attempt->fresh();
    }

    public function queueAutomaticVirtualAccount(Payment $payment): void
    {
        $payment->loadMissing('registration.user');

        $attempt = $this->automaticAttempt($payment->registration, 'virtual_account', $payment->id);
        SendVirtualAccountMail::dispatch($payment->id, $attempt->id);
    }

    public function queueAutomaticAnnouncement(Announcement $announcement): void
    {
        $announcement->loadMissing('registration.user');

        $attempt = $this->automaticAttempt($announcement->registration, 'announcement', $announcement->id);
        SendAnnouncementPublishedMail::dispatch($announcement->id, $attempt->id);
    }

    private function automaticAttempt(Registration $registration, string $type, int $sourceId): MailDeliveryAttempt
    {
        $registration->loadMissing('user');

        return MailDeliveryAttempt::query()->create([
            'unit_id' => $registration->unit_id,
            'registration_id' => $registration->id,
            'requested_by' => null,
            'type' => $type,
            'origin' => 'automatic',
            'status' => 'queued',
            'source_id' => $sourceId,
            'recipient_email' => $registration->user->email,
        ]);
    }

    public function markSent(int $id): void
    {
        MailDeliveryAttempt::query()->whereKey($id)->where('status', 'queued')->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markFailed(int $id, string $message): void
    {
        MailDeliveryAttempt::query()->whereKey($id)->where('status', 'queued')->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => mb_substr($message, 0, 1000),
        ]);
    }

    public function markSkipped(int $id): void
    {
        MailDeliveryAttempt::query()->whereKey($id)->where('status', 'queued')->update([
            'status' => 'skipped',
        ]);
    }

    public function destinationMatches(int $deliveryId, string $email): bool
    {
        return MailDeliveryAttempt::query()
            ->whereKey($deliveryId)
            ->where('status', 'queued')
            ->where('recipient_email', $email)
            ->exists();
    }
}
