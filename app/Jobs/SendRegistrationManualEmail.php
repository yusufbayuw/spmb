<?php

namespace App\Jobs;

use App\Models\MailDeliveryAttempt;
use App\Models\Registration;
use App\Models\RegistrationManualEmail;
use App\Services\RegistrationEmailDeliveryService;
use App\Services\AdmissionTerminology;
use App\Services\RegistrationManualEmailService;
use App\Services\SpmbNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendRegistrationManualEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 45;

    public function __construct(public int $deliveryAttemptId)
    {
        $this->onQueue((string) config('spmb.mail.queue', 'emails'));
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(RegistrationEmailDeliveryService $delivery, RegistrationManualEmailService $manual): void
    {
        $attempt = MailDeliveryAttempt::query()->find($this->deliveryAttemptId);
        if (! $attempt || $attempt->status !== 'queued' || $attempt->type !== 'manual_message') {
            return;
        }

        $delivery->markAttempted($attempt->id);
        $message = RegistrationManualEmail::query()
            ->where('mail_delivery_attempt_id', $attempt->id)->first();
        $registration = Registration::query()->with('user')->find($attempt->registration_id);
        $actor = $attempt->requester;

        // Refuse delivery if applicant address, unit, role or staff authorization changed.
        if (! $message || ! $registration || ! $actor
            || ! $manual->canCompose($actor, $registration)
            || (int) $registration->unit_id !== (int) $attempt->unit_id
            || ! $delivery->destinationMatches($attempt->id, $registration->user->email)) {
            $delivery->markSkipped($attempt->id);
            return;
        }

        $body = $message->body
            ."\n\nHormat kami,\n".app(AdmissionTerminology::class)->portalName();

        Mail::raw($body, fn ($mail) => $mail
            ->to($attempt->recipient_email)
            ->subject($message->subject));

        $delivery->markSent($attempt->id);
    }

    public function failed(?Throwable $exception): void
    {
        $service = app(RegistrationEmailDeliveryService::class);
        $service->markFailed($this->deliveryAttemptId, $exception?->getMessage() ?: 'Queue failure');

        $attempt = MailDeliveryAttempt::query()->find($this->deliveryAttemptId);
        app(SpmbNotificationService::class)->deliveryFailure(
            $attempt,
            'email.manual_message',
            'Email komunikasi manual gagal setelah percobaan ulang',
            $attempt?->unit_id,
            $attempt?->registration_id,
        );
    }
}
