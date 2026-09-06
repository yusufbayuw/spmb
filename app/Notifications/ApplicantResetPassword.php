<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as LaravelResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

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
            ->subject('Atur Ulang Kata Sandi | SPMB Taruna Bakti')
            ->markdown('mail.applicant-reset-password', [
                'applicationName' => config('app.name', 'SPMB Taruna Bakti'),
                'expiresInMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
                'url' => $this->resetUrl($notifiable),
            ]);
    }

    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        return $this->url ?? parent::resetUrl($notifiable);
    }
}
