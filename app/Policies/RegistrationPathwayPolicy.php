<?php

namespace App\Policies;

use App\Models\RegistrationPathway;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RegistrationPathwayPolicy extends ShieldResourcePolicy
{
    protected const KEY = 'registrationpathway';

    public function view(User $user, Model $record): bool
    {
        return parent::view($user, $record)
            && $this->belongsToUsersUnit($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        return parent::update($user, $record)
            && $this->belongsToUsersUnit($user, $record);
    }

    private function belongsToUsersUnit(User $user, Model $record): bool
    {
        return $record instanceof RegistrationPathway
            && (! $user->isTU() || $user->unit_id === $record->unit_id);
    }
}
