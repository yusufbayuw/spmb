<?php

namespace App\Jobs;

use App\Mail\AnnouncementPublishedMail;
use App\Models\Announcement;
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

    public function __construct(public int $announcementId)
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
        $announcement = Announcement::query()->with('registration.user')->findOrFail($this->announcementId);
        Mail::to($announcement->registration->user->email)->send(new AnnouncementPublishedMail($announcement));

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
