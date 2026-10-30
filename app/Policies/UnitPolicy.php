<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy extends ShieldResourcePolicy
{
    protected const KEY = 'unit';

    /**
     * Own-unit administrator access is explicit, not a blanket unit update
     * permission. The edit page also enforces a strict attribute allowlist.
     */
    private function managesOwnUnit(User $user, Unit $unit): bool
    {
        return $user->is_active
            && $user->isAdminUnit()
            && $user->hasOperationalUnitAccess()
            && $unit->isOperational()
            && (int) $user->unit_id === (int) $unit->id;
    }

    public function viewAny(User $user): bool
    {
        return ($user->is_active && $user->isAdminUnit() && $user->hasOperationalUnitAccess())
            || parent::viewAny($user);
    }

    public function view(User $user, \Illuminate\Database\Eloquent\Model $record): bool
    {
        return ($record instanceof Unit && $this->managesOwnUnit($user, $record))
            || parent::view($user, $record);
    }

    public function update(User $user, \Illuminate\Database\Eloquent\Model $record): bool
    {
        return ($record instanceof Unit && $this->managesOwnUnit($user, $record))
            || parent::update($user, $record);
    }

    public function create(User $user): bool
    {
        return ! $user->isAdminUnit() && parent::create($user);
    }

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
