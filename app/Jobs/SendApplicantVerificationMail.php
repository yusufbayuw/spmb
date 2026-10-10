<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ApplicantVerifyEmail;
use App\Services\ApplicantEmailVerificationUrl;
use App\Services\AuditTrail;
use App\Services\RegistrationEmailDeliveryService;
use App\Services\SpmbNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendApplicantVerificationMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 45;

    public function __construct(public int $userId, public int $deliveryAttemptId)
    {
        $this->onQueue((string) config('spmb.mail.queue', 'emails'));
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(RegistrationEmailDeliveryService $deliveries, AuditTrail $audit): void
    {
        if ($this->deliveryAttemptId !== null) {
            app(RegistrationEmailDeliveryService::class)->markAttempted($this->deliveryAttemptId);
        }
        $user = User::query()->findOrFail($this->userId);

        if (! $user->is_active || $user->hasVerifiedEmail()
            || ! $deliveries->destinationMatches($this->deliveryAttemptId, $user->email)) {
            $deliveries->markSkipped($this->deliveryAttemptId);
            return;
        }

        $notification = app(ApplicantVerifyEmail::class);
        $notification->url = app(ApplicantEmailVerificationUrl::class)->for($user);

        // sendNow executes the mail transport inside this queued job, rather
        // than queuing a second notification and falsely reporting delivery.
        Notification::sendNow($user, $notification, ['mail']);

        $deliveries->markSent($this->deliveryAttemptId);

        $audit->record(
            'mail.verification_sent',
            $user,
            metadata: ['delivery_attempt_id' => $this->deliveryAttemptId],
            description: 'Email verifikasi diminta ulang oleh petugas dan diserahkan ke transport',
        );
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception?->getMessage() ?: 'Unknown queue failure';
        app(RegistrationEmailDeliveryService::class)->markFailed($this->deliveryAttemptId, $message);

        $attempt = \App\Models\MailDeliveryAttempt::query()->find($this->deliveryAttemptId);

        app(AuditTrail::class)->record(
            'mail.verification_failed',
            null,
            metadata: ['delivery_attempt_id' => $this->deliveryAttemptId],
            unitId: $attempt?->unit_id,
            registrationId: $attempt?->registration_id,
            description: 'Email verifikasi gagal setelah seluruh retry',
        );

        app(SpmbNotificationService::class)->deliveryFailure(
            $attempt,
            'email.verification',
            $message,
            $attempt?->unit_id,
            $attempt?->registration_id,
        );
    }
}
