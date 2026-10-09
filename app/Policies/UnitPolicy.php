<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy extends ShieldResourcePolicy
{
    protected const KEY = 'unit';

    /**
     * Operational test bookings and schedule adjustments do not confer the
     * broader right to edit versioned registration configuration.
     */
    public function manageTestOperations(User $user, Unit $unit): bool
    {
        return $user->is_active
            && $unit->isOperational()
            && ($user->isAdmin()
                || ($user->isTU()
                    && (int) $user->unit_id === (int) $unit->id
                    && ($user->isAdminUnit() || $user->can('record_result_admissiontestresult'))));
    }

    public function configureRegistration(User $user, Unit $unit): bool
    {
        return $user->is_active
            && $unit->isOperational()
            && ($user->isAdmin() || ($user->isAdminUnit() && (int) $user->unit_id === (int) $unit->id));
    }
}
