<?php

namespace App\Observers;

use App\Models\Registration;
use App\Services\SpmbNotificationService;

class RegistrationNotificationObserver
{
    public function created(Registration $registration): void
    {
        app(SpmbNotificationService::class)->registrationSubmitted($registration);
    }
}
