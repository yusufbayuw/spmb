<?php

namespace App\Jobs;

use App\Models\MailDeliveryAttempt;
use App\Models\Registration;
use App\Services\RegistrationEmailDeliveryService;
use App\Services\SpmbNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendRegistrationActionReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 45;

    public function __construct(public int $registrationId, public int $deliveryAttemptId)
    {
        $this->onQueue((string) config('spmb.mail.queue', 'emails'));
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(RegistrationEmailDeliveryService $service): void
    {
        $service->markAttempted($this->deliveryAttemptId);
        $attempt = MailDeliveryAttempt::query()->findOrFail($this->deliveryAttemptId);
        $registration = Registration::query()->with(['user', 'latestPayment', 'admissionOffer', 'testBookings.session'])
            ->find($this->registrationId);

        if (! $registration || ! $registration->isOperational() || ! $registration->user?->is_active
            || ! $service->destinationMatches($attempt->id, $registration->user->email)
            || ! array_key_exists($attempt->type, $service->availableTypes($registration))) {
            $service->markSkipped($this->deliveryAttemptId);
            return;
        }

        [$subject, $message] = match ($attempt->type) {
            'revision_reminder' => ['Perbaikan data atau berkas diperlukan',
                'Terdapat data atau berkas yang perlu direvisi. Masuk ke portal untuk melihat catatan dan mengunggah perbaikan.'],
            'payment_reminder' => ['Pengingat bukti transfer',
                'Virtual Account sudah tersedia tetapi bukti transfer belum diterima. Periksa rincian pembayaran dan unggah bukti jika sudah transfer.'],
            'test_reminder' => ['Pengingat tahapan tes',
                'Tahapan tes masih memerlukan perhatian. Periksa jadwal, pilihan sesi, dan status konfirmasi melalui portal.'],
            'offer_reminder' => ['Konfirmasi penerimaan masih tertunda',
                'Konfirmasikan penawaran penerimaan sebelum batas waktu: '
                    .$registration->admissionOffer->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i').'.'],
            default => [null, null],
        };

        if (! $subject) {
            $service->markSkipped($this->deliveryAttemptId);
            return;
        }

        $link = url('/pendaftar/status/'.$registration->uuid);
        $body = "Yth. Bapak/Ibu Pendaftar,\n\n{$message}\n\nBuka: {$link}\n\nAbaikan pengingat jika sudah menyelesaikan tindakan tersebut.\n\nHormat kami,\n".config('spmb.portal.name', 'SPMB');
        Mail::raw($body, fn ($mail) => $mail->to($registration->user->email)
            ->subject($subject.' | '.config('spmb.portal.name', 'SPMB')));
        $service->markSent($this->deliveryAttemptId);
    }

    public function failed(?Throwable $exception): void
    {
        $service = app(RegistrationEmailDeliveryService::class);
        $service->markFailed($this->deliveryAttemptId, $exception?->getMessage() ?: 'Queue failure');
        $attempt = MailDeliveryAttempt::query()->find($this->deliveryAttemptId);
        app(SpmbNotificationService::class)->deliveryFailure(
            $attempt, 'email.reminder', 'Pengingat gagal setelah retry',
            $attempt?->unit_id, $attempt?->registration_id
        );
    }
}
