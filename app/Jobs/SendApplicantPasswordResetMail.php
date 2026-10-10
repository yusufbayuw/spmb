<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ApplicantResetPassword;
use App\Services\AuditTrail;
use App\Services\RegistrationEmailDeliveryService;
use App\Services\SpmbNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Throwable;

class SendApplicantPasswordResetMail implements ShouldQueue
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

    public function handle(RegistrationEmailDeliveryService $service, AuditTrail $audit): void
    {
        $service->markAttempted($this->deliveryAttemptId);
        $user = User::query()->find($this->userId);
        if (! $user || ! $user->is_active || ! $user->hasRole('pendaftar')
            || $user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])
            || ! $service->destinationMatches($this->deliveryAttemptId, $user->email)) {
            $service->markSkipped($this->deliveryAttemptId);
            return;
        }

        // Only the standard Laravel password broker can mint this token.
        // It is never returned to staff or written to mail_delivery_attempts.
        $token = Password::broker()->createToken($user);
        Notification::sendNow($user, new ApplicantResetPassword($token), ['mail']);
        $service->markSent($this->deliveryAttemptId);
        $audit->record('mail.password_reset_sent', $user,
            metadata: ['delivery_attempt_id' => $this->deliveryAttemptId],
            description: 'Tautan pemulihan password diserahkan ke transport email');
    }

    public function failed(?Throwable $exception): void
    {
        $service = app(RegistrationEmailDeliveryService::class);
        $service->markFailed($this->deliveryAttemptId, $exception?->getMessage() ?: 'Queue failure');
        $attempt = \App\Models\MailDeliveryAttempt::query()->find($this->deliveryAttemptId);
        app(SpmbNotificationService::class)->deliveryFailure(
            $attempt, 'email.password_reset', 'Pemulihan password gagal setelah retry',
            $attempt?->unit_id, $attempt?->registration_id
        );
    }
}
