<?php

namespace App\Jobs;

use App\Mail\AnnouncementPublishedMail;
use App\Models\Announcement;
use App\Services\RegistrationEmailDeliveryService;
use App\Services\AuditTrail;
use App\Services\SpmbNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAnnouncementPublishedMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 45;

    public function __construct(public int $announcementId, public ?int $deliveryAttemptId = null)
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
        if ($this->deliveryAttemptId !== null) {
            app(RegistrationEmailDeliveryService::class)->markAttempted($this->deliveryAttemptId);
        }
        $announcement = Announcement::query()->with('registration.user')->findOrFail($this->announcementId);
        $recipient = $announcement->registration->user->email;
        if ($this->deliveryAttemptId !== null && ! app(RegistrationEmailDeliveryService::class)->destinationMatches($this->deliveryAttemptId, $recipient)) {
            app(RegistrationEmailDeliveryService::class)->markSkipped($this->deliveryAttemptId);
            return;
        }

        if ($this->deliveryAttemptId !== null
            && \App\Models\MailDeliveryAttempt::query()->whereKey($this->deliveryAttemptId)->where('origin', 'manual')->exists()
            && $announcement->status !== 'published') {
            app(RegistrationEmailDeliveryService::class)->markSkipped($this->deliveryAttemptId);
            return;
        }

        Mail::to($recipient)->send(new AnnouncementPublishedMail($announcement));
        if ($this->deliveryAttemptId !== null) {
            app(RegistrationEmailDeliveryService::class)->markSent($this->deliveryAttemptId);
        }

        $announcement->update(['email_sent_at' => now()]);

        $audit->record(
            'mail.announcement_sent',
            $announcement,
            metadata: ['recipient' => $announcement->registration->user->email],
            description: 'Email pengumuman berhasil dikirim',
        );
    }

    public function failed(?Throwable $exception): void
    {
        $announcement = Announcement::query()->with('registration')->find($this->announcementId);
        if ($this->deliveryAttemptId !== null) {
            app(RegistrationEmailDeliveryService::class)->markFailed($this->deliveryAttemptId, $exception?->getMessage() ?: 'Unknown queue failure');
        }
        $message = $exception?->getMessage() ?: 'Unknown queue failure';

        app(AuditTrail::class)->record(
            'mail.announcement_failed',
            $announcement,
            metadata: ['error' => $message],
            description: 'Email pengumuman gagal setelah seluruh retry',
        );

        app(SpmbNotificationService::class)->deliveryFailure(
            $announcement,
            'email.announcement',
            $message,
            $announcement?->registration?->unit_id,
            $announcement?->registration_id,
        );
    }
}
