<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class ShieldResourcePolicy
{
    protected const KEY = '';

    public function viewAny(User $user): bool { return $user->can('view_any_'.static::KEY); }
    public function view(User $user, Model $record): bool { return $user->can('view_'.static::KEY); }
    public function create(User $user): bool { return $user->can('create_'.static::KEY); }
    public function update(User $user, Model $record): bool { return $user->can('update_'.static::KEY); }

    // Business records are lifecycle-managed. Permanent deletion is denied at
    // the policy layer so future Filament resources cannot accidentally expose it.
    public function delete(User $user, Model $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restore(User $user, Model $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDelete(User $user, Model $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }

    public function replicate(User $user, Model $record): bool { return $user->can('replicate_'.static::KEY); }
    public function reorder(User $user): bool { return $user->can('reorder_'.static::KEY); }
}
