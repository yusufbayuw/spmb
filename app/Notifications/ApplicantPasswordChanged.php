<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicantPasswordChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(config('spmb.mail.queue', 'emails'));
        $this->afterCommit();
    }

    /**
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kata Sandi Berhasil Diubah | SPMB Taruna Bakti')
            ->markdown('mail.applicant-password-changed', [
                'applicationName' => config('app.name', 'SPMB Taruna Bakti'),
            ]);
    }
}
