<?php

namespace App\Filament\Applicant\Pages\Auth;

use App\Models\User;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class Register extends BaseRegister
{
    protected function handleRegistration(array $data): Model
    {
        $data['role'] = 'user';
        $data['is_active'] = true;

        $user = parent::handleRegistration($data);

        $role = Role::firstOrCreate([
            'name' => 'pendaftar',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }

    protected function sendEmailVerificationNotification(Model $user): void
    {
        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail() || ! $user instanceof User) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
