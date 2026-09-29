<?php

namespace App\Notifications;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SpmbDatabaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(
        public string $event,
        public string $category,
        public string $title,
        public ?string $body = null,
        public string $status = 'info',
        public ?string $icon = null,
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public ?string $registrationUuid = null,
        public ?string $unitUuid = null,
        public array $metadata = [],
    ) {
        $this->onQueue((string) config('spmb.notifications.queue', 'notifications'));
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->pushEnabledFor($notifiable)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function viaQueues(): array
    {
        $queue = (string) config('spmb.notifications.queue', 'notifications');

        return [
            'database' => $queue,
            WebPushChannel::class => $queue,
        ];
    }

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->icon($this->icon);

        match ($this->status) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            default => $notification->info(),
        };

        if ($this->actionUrl && $this->actionLabel) {
            $notification->actions([
                Action::make('view')
                    ->label($this->actionLabel)
                    ->button()
                    ->url($this->actionUrl)
                    ->markAsRead(),
            ]);
        }

        return array_merge($notification->getDatabaseMessage(), [
            'spmb_event' => $this->event,
            'category' => $this->category,
            'registration_uuid' => $this->registrationUuid,
            'unit_uuid' => $this->unitUuid,
            'metadata' => $this->metadata,
        ]);
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $url = $this->actionUrl ?: url('/dashboard');
        $tagContext = $this->registrationUuid ?: $this->unitUuid ?: 'global';
        $tag = 'spmb-'.substr(hash('sha256', $this->event.'|'.$tagContext), 0, 32);

        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body ?: 'Ada pembaruan pada proses SPMB.')
            ->icon(asset('images/pwa/icon-192.png'))
            ->badge(asset('images/pwa/badge-96.png'))
            ->tag($tag)
            ->renotify()
            ->data([
                'url' => $url,
                'event' => $this->event,
                'category' => $this->category,
                'registration_uuid' => $this->registrationUuid,
                'unit_uuid' => $this->unitUuid,
            ])
            ->options([
                'TTL' => 86400,
                'urgency' => in_array($this->category, ['work_queue', 'operational', 'announcement'], true)
                    ? 'high'
                    : 'normal',
            ]);
    }

    private function pushEnabledFor(object $notifiable): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'))
            && method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists();
    }
}
