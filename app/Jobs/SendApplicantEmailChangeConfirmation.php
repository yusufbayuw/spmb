<?php

namespace App\Jobs;

use App\Models\MailDeliveryAttempt;
use App\Models\PendingApplicantEmailChange;
use App\Services\RegistrationEmailDeliveryService;
use App\Services\SpmbNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class SendApplicantEmailChangeConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 45;

    public function __construct(public int $changeId, public int $deliveryAttemptId)
    {
        $this->onQueue((string) config('spmb.mail.queue', 'emails'));
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(RegistrationEmailDeliveryService $delivery): void
    {
        $delivery->markAttempted($this->deliveryAttemptId);
        $change = PendingApplicantEmailChange::query()->with('user')->find($this->changeId);
        $attempt = MailDeliveryAttempt::query()->find($this->deliveryAttemptId);

        if (! $change || ! $attempt || $attempt->status !== 'queued'
            || $attempt->type !== 'email_change' || $attempt->source_id !== $change->id
            || $attempt->recipient_email !== $change->new_email
            || $change->status !== 'pending' || $change->expires_at->isPast()
            || ! $change->user?->is_active || $change->user->email !== $change->old_email) {
            $delivery->markSkipped($this->deliveryAttemptId);
            return;
        }

        $relative = URL::temporarySignedRoute('applicant.email-change.confirm', $change->expires_at,
            ['emailChange' => $change->uuid], absolute: false);
        $link = rtrim((string) config('app.url'), '/').'/'.ltrim($relative, '/');
        $body = "Permintaan koreksi alamat email akun SPMB.\n\n"
            ."Untuk menyetujui, masuk dengan akun pendaftar Anda lalu buka tautan ini sebelum kedaluwarsa:\n{$link}\n\n"
            ."Jika tidak mengajukan perubahan ini, abaikan pesan dan hubungi unit penerimaan.\n\n"
            .config('spmb.portal.name', 'SPMB');
        Mail::raw($body, fn ($mail) => $mail->to($change->new_email)
            ->subject('Konfirmasi Alamat Email Baru | '.config('spmb.portal.name', 'SPMB')));
        $delivery->markSent($this->deliveryAttemptId);
    }

    public function failed(?Throwable $exception): void
    {
        app(RegistrationEmailDeliveryService::class)->markFailed(
            $this->deliveryAttemptId, $exception?->getMessage() ?: 'Queue failure'
        );
        $attempt = MailDeliveryAttempt::query()->find($this->deliveryAttemptId);
        app(SpmbNotificationService::class)->deliveryFailure(
            $attempt, 'email.email_change', 'Konfirmasi email baru gagal setelah retry',
            $attempt?->unit_id, $attempt?->registration_id
        );
    }
}
