<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\ApplicantVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Tests\TestCase;

class ApplicantVerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_email_uses_formal_indonesian_content(): void
    {
        $user = User::factory()->create();
        $notification = app(ApplicantVerifyEmail::class);
        $notification->url = 'https://spmb.test/pendaftar/email-verification/uuid-verify/'.$user->uuid.'/hash';

        $message = $notification->toMail($user);
        $html = (string) $message->render();
        $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame('Verifikasi Alamat Email | SPMB Taruna Bakti', $message->subject);
        $this->assertSame('mail.applicant-email-verification', $message->markdown);
        $this->assertStringContainsString('Yth. Bapak/Ibu Pendaftar', $html);
        $this->assertStringContainsString('Verifikasi Alamat Email', $html);
        $this->assertStringContainsString('Jangan bagikan tautan ini kepada pihak lain.', $html);
        $this->assertStringContainsString('Hormat kami', $text);
        $this->assertStringNotContainsString('Hello!', $html);
        $this->assertStringNotContainsString('Please click the button below', $html);
        $this->assertStringNotContainsString('All rights reserved.', $html);
    }
}
