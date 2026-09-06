<?php

namespace App\Filament\Applicant\Pages\Auth;

use App\Models\User;
use Filament\Pages\Auth\EmailVerification\EmailVerificationPrompt as BaseEmailVerificationPrompt;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class EmailVerificationPrompt extends BaseEmailVerificationPrompt
{
    protected function sendEmailVerificationNotification(MustVerifyEmail $user): void
    {
        if ($user->hasVerifiedEmail() || ! $user instanceof User) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
