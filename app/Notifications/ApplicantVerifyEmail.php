<?php

namespace App\Notifications;

use Filament\Notifications\Auth\VerifyEmail as FilamentVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class ApplicantVerifyEmail extends FilamentVerifyEmail
{
    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifikasi Alamat Email | '.config('spmb.portal.name', 'SPMB'))
            ->markdown('mail.applicant-email-verification', [
                'applicationName' => config('spmb.portal.name', config('app.name', 'SPMB')),
                'expiresInMinutes' => (int) config('auth.verification.expire', 60),
                'url' => $this->verificationUrl($notifiable),
            ]);
    }
}
