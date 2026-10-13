<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as LaravelResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class ApplicantResetPassword extends LaravelResetPassword implements ShouldQueue
{
    use Queueable;

    public ?string $url = null;

    public function __construct(#[\SensitiveParameter] $token)
    {
        parent::__construct($token);

        $this->onQueue(config('spmb.mail.queue', 'emails'));
        $this->afterCommit();
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi | '.app(\App\Services\AdmissionTerminology::class)->portalName())
            ->markdown('mail.applicant-reset-password', [
                'applicationName' => app(\App\Services\AdmissionTerminology::class)->portalName(),
                'expiresInMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
                'url' => $this->resetUrl($notifiable),
            ]);
    }

    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->hasRole('pendaftar')
            && ! $notifiable->hasAnyRole(['super_admin', 'admin_unit', 'tu'])) {
            // Filament assigns an absolute signed URL, which can fail behind
            // Cloudflare / a reverse proxy when Laravel sees a different host
            // or scheme. Sign the route path, then use the configured public
            // application URL for delivery. The password broker token remains
            // secret, time-limited, and validated by Filament as before.
            $relativeUrl = URL::signedRoute(
                'filament.pendaftar.auth.password-reset.reset',
                [
                    'email' => $notifiable->getEmailForPasswordReset(),
                    'token' => $this->token,
                ],
                absolute: false,
            );

            return rtrim((string) config('app.url'), '/').'/'.ltrim($relativeUrl, '/');
        }

        return $this->url ?? parent::resetUrl($notifiable);
    }
}
