<?php

namespace App\Jobs;

use App\Mail\VirtualAccountMail;
use App\Models\Payment;
use App\Services\RegistrationEmailDeliveryService;
use App\Services\AuditTrail;
use App\Services\SpmbNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendVirtualAccountMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 45;

    public function __construct(public int $paymentId, public ?int $deliveryAttemptId = null)
    {
        $this->onQueue((string) config('spmb.mail.queue', 'emails'));
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(AuditTrail $audit): void
    {
        $payment = Payment::query()->with(['registration.user', 'registration.unit'])->findOrFail($this->paymentId);
        $recipient = $payment->registration->user->email;
        if ($this->deliveryAttemptId !== null && ! app(RegistrationEmailDeliveryService::class)->destinationMatches($this->deliveryAttemptId, $recipient)) {
            app(RegistrationEmailDeliveryService::class)->markSkipped($this->deliveryAttemptId);
            return;
        }

        // Manual retries must not send old VA data after the registration moves on.
        if ($this->deliveryAttemptId !== null
            && \App\Models\MailDeliveryAttempt::query()->whereKey($this->deliveryAttemptId)->where('origin', 'manual')->exists()
            && ($payment->registration->current_stage !== 'payment' || ! in_array($payment->status, ['pending', 'rejected'], true))) {
            app(RegistrationEmailDeliveryService::class)->markSkipped($this->deliveryAttemptId);
            return;
        }

        Mail::to($recipient)->send(new VirtualAccountMail($payment));
        if ($this->deliveryAttemptId !== null) {
            app(RegistrationEmailDeliveryService::class)->markSent($this->deliveryAttemptId);
        }

        $audit->record(
            'mail.virtual_account_sent',
            $payment,
            metadata: ['recipient' => $payment->registration->user->email],
            description: 'Email virtual account berhasil dikirim',
        );
    }

    public function failed(?Throwable $exception): void
    {
        $payment = Payment::query()->with('registration')->find($this->paymentId);
        if ($this->deliveryAttemptId !== null) {
            app(RegistrationEmailDeliveryService::class)->markFailed($this->deliveryAttemptId, $exception?->getMessage() ?: 'Unknown queue failure');
        }
        $message = $exception?->getMessage() ?: 'Unknown queue failure';

        app(AuditTrail::class)->record(
            'mail.virtual_account_failed',
            $payment,
            metadata: ['error' => $message],
            description: 'Email virtual account gagal setelah seluruh retry',
        );

        app(SpmbNotificationService::class)->deliveryFailure(
            $payment,
            'email.virtual_account',
            $message,
            $payment?->registration?->unit_id,
            $payment?->registration_id,
        );
    }
}
