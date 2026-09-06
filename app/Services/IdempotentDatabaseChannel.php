<?php

namespace App\Services;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

class IdempotentDatabaseChannel extends DatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        $payload = $this->buildPayload($notifiable, $notification);

        return $notifiable->routeNotificationFor('database', $notification)->createOrFirst(['id' => $payload['id']], $payload);
    }
}
