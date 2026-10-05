<?php

namespace App\Services;

use App\Models\Registration;

class TestCardEligibilityService
{
    public function canPrint(Registration $registration): bool
    {
        return app(TestScheduleConfirmationService::class)->isConfirmed($registration);
    }
}
