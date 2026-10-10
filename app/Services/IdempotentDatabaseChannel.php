<?php

namespace App\Services;

use App\Notifications\SpmbDatabaseNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

class IdempotentDatabaseChannel extends DatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        $payload = $this->buildPayload($notifiable, $notification);

        // NotificationSender can assign a new notification->id when sendNow is
        // retried. The dedicated delivery UUID is created once per event and
        // serialized with the queued notification, preventing duplicate rows.
        if ($notification instanceof SpmbDatabaseNotification) {
            // A single Laravel notification may target multiple recipients.
            // Generate a stable *per recipient* UUID, never the same PK for all.
            $payload['id'] = Uuid::uuid5(Uuid::NAMESPACE_URL,
                $notification->deliveryUuid.'|'.$notifiable->getMorphClass().'|'.$notifiable->getKey()
            )->toString();
            // Preserve the prior contract for callers inspecting notification->id.
            $notification->id = $payload['id'];
        }

        return $notifiable->routeNotificationFor('database', $notification)->createOrFirst(['id' => $payload['id']], $payload);
    }
}
