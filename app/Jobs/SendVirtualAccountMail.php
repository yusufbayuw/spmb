<?php

namespace App\Jobs;

use App\Mail\VirtualAccountMail;
use App\Models\Payment;
use App\Services\AuditTrail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendVirtualAccountMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 45;

    public function __construct(public int $paymentId)
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
        Mail::to($payment->registration->user->email)->send(new VirtualAccountMail($payment));

        $audit->record(
            'mail.virtual_account_sent',
            $payment,
            metadata: ['recipient' => $payment->registration->user->email],
            description: 'Email virtual account berhasil dikirim',
        );
    }

    public function failed(?Throwable $exception): void
    {
        $payment = Payment::query()->find($this->paymentId);

        app(AuditTrail::class)->record(
            'mail.virtual_account_failed',
            $payment,
            metadata: ['error' => $exception?->getMessage()],
            description: 'Email virtual account gagal setelah seluruh retry',
        );
    }
}
