<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    private function allowed(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->allowed($user) && $role->name !== 'super_admin';
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Role $role): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
