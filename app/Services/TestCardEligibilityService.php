<?php

namespace App\Services;

use App\Models\Registration;

class TestCardEligibilityService
{
    public function canPrint(Registration $registration): bool
    {
        $registration->loadMissing('configuration');

        return $registration->isOperational()
            && $registration->testCardEnabled()
            && app(TestScheduleConfirmationService::class)->isConfirmed($registration);
    }
}
