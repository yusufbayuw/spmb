<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\TestBooking;

class TestCardEligibilityService
{
    public function canPrint(Registration $registration): bool
    {
        $requiredTestIds = collect($registration->configuredTests())
            ->filter(fn (array $test): bool => (bool) ($test['is_required'] ?? false))
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($requiredTestIds->isEmpty()) {
            return false;
        }

        $scheduledRequiredTestIds = TestBooking::query()
            ->where('registration_id', $registration->id)
            ->whereIn('admission_test_id', $requiredTestIds->all())
            ->whereNotNull('test_session_id')
            ->pluck('admission_test_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();

        return $requiredTestIds->diff($scheduledRequiredTestIds)->isEmpty();
    }
}
