<?php

namespace App\Mail;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnnouncementPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Announcement $announcement) {}

    public function build(): self
    {
        return $this->subject(\App\Support\AdmissionTerminology::visibleText($this->announcement->title ?: 'Pengumuman '.\App\Support\AdmissionTerminology::label()))
            ->markdown('emails.announcement-published');
    }
}
