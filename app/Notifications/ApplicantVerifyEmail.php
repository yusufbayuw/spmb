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
            ->subject('Verifikasi Alamat Email | SPMB Taruna Bakti')
            ->markdown('mail.applicant-email-verification', [
                'applicationName' => config('app.name', 'SPMB Taruna Bakti'),
                'expiresInMinutes' => (int) config('auth.verification.expire', 60),
                'url' => $this->verificationUrl($notifiable),
            ]);
    }
}
