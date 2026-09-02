<?php

namespace App\Notifications;

use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

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
        public ?int $registrationId = null,
        public ?int $unitId = null,
        public array $metadata = [],
    ) {
        $this->onQueue((string) config('spmb.notifications.queue', 'notifications'));
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function viaQueues(): array
    {
        return ['database' => (string) config('spmb.notifications.queue', 'notifications')];
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
            'registration_id' => $this->registrationId,
            'unit_id' => $this->unitId,
            'metadata' => $this->metadata,
        ]);
    }
}
