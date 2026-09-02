<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VirtualAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function build(): self
    {
        return $this->subject('Virtual Account Pendaftaran SPMB')
            ->view('emails.virtual-account');
    }
}
