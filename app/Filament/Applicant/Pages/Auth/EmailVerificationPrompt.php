<?php

namespace App\Filament\Applicant\Pages\Auth;

use App\Models\User;
use Filament\Pages\Auth\EmailVerification\EmailVerificationPrompt as BaseEmailVerificationPrompt;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class EmailVerificationPrompt extends BaseEmailVerificationPrompt
{
    // Filament 3's default view only has a generic resend prompt.
    // Keep its throttled resend action but show practical instructions.
    protected static string $view = 'filament.applicant.pages.auth.email-verification-prompt';

    public function getHeading(): string
    {
        return 'Verifikasi Alamat Email';
    }

    public function resendNotificationAction(): \Filament\Actions\Action
    {
        return parent::resendNotificationAction()
            ->label('Kirim Ulang Email Verifikasi');
    }

    protected function sendEmailVerificationNotification(MustVerifyEmail $user): void
    {
        if ($user->hasVerifiedEmail() || ! $user instanceof User) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }
}
