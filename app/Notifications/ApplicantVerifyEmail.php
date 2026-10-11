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
            ->subject('Verifikasi Alamat Email | '.app(\App\Services\AdmissionTerminology::class)->portalName())
            ->markdown('mail.applicant-email-verification', [
                'applicationName' => app(\App\Services\AdmissionTerminology::class)->portalName(),
                'expiresInMinutes' => (int) config('auth.verification.expire', 60),
                'url' => $this->verificationUrl($notifiable),
            ]);
    }
}
