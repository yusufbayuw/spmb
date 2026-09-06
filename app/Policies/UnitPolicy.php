<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy extends ShieldResourcePolicy
{
    protected const KEY = 'unit';

    public function configureRegistration(User $user, Unit $unit): bool
    {
        return $user->is_active && ($user->isAdmin() || ($user->isTU() && (int) $user->unit_id === $unit->id));
    }
}
