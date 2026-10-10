<?php

namespace App\Services;

use App\Notifications\SpmbDatabaseNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

class IdempotentDatabaseChannel extends DatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        $payload = $this->buildPayload($notifiable, $notification);

        // NotificationSender can assign a new notification->id when sendNow is
        // retried. The dedicated delivery UUID is created once per event and
        // serialized with the queued notification, preventing duplicate rows.
        if ($notification instanceof SpmbDatabaseNotification) {
            $payload['id'] = $notification->deliveryUuid;
        }

        return $notifiable->routeNotificationFor('database', $notification)->createOrFirst(['id' => $payload['id']], $payload);
    }
}
