<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy extends ShieldResourcePolicy
{
    protected const KEY = 'unit';

    public function configureRegistration(User $user, Unit $unit): bool
    {
        return $user->is_active
            && $unit->isOperational()
            && ($user->isAdmin() || ($user->isAdminUnit() && (int) $user->unit_id === (int) $unit->id));
    }
}
