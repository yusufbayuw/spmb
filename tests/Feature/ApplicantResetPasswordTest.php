<?php

namespace Tests\Feature;

use App\Mail\AnnouncementPublishedMail;
use App\Mail\VirtualAccountMail;
use App\Models\Announcement;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Notifications\ApplicantPasswordChanged;
use App\Notifications\ApplicantResetPassword;
use App\Notifications\ApplicantVerifyEmail;
use Filament\Notifications\Auth\ResetPassword as FilamentResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApplicantResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_email_uses_formal_indonesian_content(): void
    {
        $user = User::factory()->create();
        $notification = new ApplicantResetPassword('reset-token');
        $notification->url = 'https://spmb.test/pendaftar/password-reset/reset?token=reset-token&email='.$user->email;

        $message = $notification->toMail($user);
        $html = (string) $message->render();
        $text = (string) app(Markdown::class)->renderText($message->markdown, $message->data());
        $normalizedText = (string) preg_replace('/\s+/', ' ', $text);

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame('emails', $notification->queue);
        $this->assertSame('Atur Ulang Kata Sandi | SPMB Taruna Bakti', $message->subject);
        $this->assertSame('mail.applicant-reset-password', $message->markdown);
        $this->assertStringContainsString('Yth. Bapak/Ibu Pendaftar', $html);
        $this->assertStringContainsString('Atur Ulang Kata Sandi', $html);
        $this->assertStringContainsString('jangan bagikan tautan ini kepada pihak lain.', $normalizedText);
        $this->assertStringContainsString('Kata sandi Anda tetap tidak berubah.', $normalizedText);
        $this->assertStringNotContainsString('Reset Password Notification', $html);
        $this->assertStringNotContainsString('You are receiving this email', $html);
        $this->assertStringNotContainsString('All rights reserved.', $html);
    }

    public function test_filament_and_legacy_reset_requests_use_the_same_notification(): void
    {
        $user = User::factory()->create();

        Notification::fake();
        $user->sendPasswordResetNotification('legacy-reset-token');

        Notification::assertSentTo(
            $user,
            ApplicantResetPassword::class,
            fn (ApplicantResetPassword $notification): bool => $notification->token === 'legacy-reset-token'
                && $notification->url === null,
        );

        $filamentNotification = app(FilamentResetPassword::class, ['token' => 'filament-reset-token']);

        $this->assertInstanceOf(ApplicantResetPassword::class, $filamentNotification);
        $this->assertSame('filament-reset-token', $filamentNotification->token);
    }

    public function test_standard_user_verification_method_uses_the_uuid_aware_indonesian_notification(): void
    {
        $user = User::factory()->unverified()->create();

        Notification::fake();
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo(
            $user,
            ApplicantVerifyEmail::class,
            fn (ApplicantVerifyEmail $notification): bool => str_contains($notification->url, $user->uuid)
                && str_contains($notification->url, '/pendaftar/email-verification/uuid-verify/'),
        );
    }

    public function test_password_reset_event_sends_a_formal_password_changed_notice(): void
    {
        $user = User::factory()->create();

        Notification::fake();
        event(new PasswordReset($user));

        Notification::assertSentTo($user, ApplicantPasswordChanged::class);

        $message = (new ApplicantPasswordChanged)->toMail($user);
        $html = (string) $message->render();

        $this->assertSame('Kata Sandi Berhasil Diubah | SPMB Taruna Bakti', $message->subject);
        $this->assertStringContainsString('Kata sandi akun Anda', $html);
        $this->assertStringContainsString('segera hubungi administrator', $html);
        $this->assertStringNotContainsString('Hello!', $html);
    }

    public function test_operational_email_templates_are_formal_indonesian(): void
    {
        $user = new User(['name' => 'Orang Tua']);
        $registration = new Registration([
            'full_name' => 'Calon Peserta',
            'registration_number' => 'SPMB-2026-0001',
        ]);
        $registration->setRelation('user', $user);

        $payment = new Payment([
            'va_number' => '1234567890',
            'amount' => 250000,
        ]);
        $payment->setRelation('registration', $registration);
        $payment->setRelation('virtualAccount', new VirtualAccount(['bank' => 'Bank Taruna']));

        $announcement = new Announcement([
            'title' => 'Pengumuman Hasil SPMB',
            'message' => 'Silakan lengkapi tindak lanjut sesuai pengumuman.',
        ]);
        $announcement->setRelation('registration', $registration);

        $virtualAccountHtml = (new VirtualAccountMail($payment))->render();
        $announcementHtml = (new AnnouncementPublishedMail($announcement))->render();

        $this->assertStringContainsString('Yth. Orang Tua', $virtualAccountHtml);
        $this->assertStringContainsString('Hormat kami', $virtualAccountHtml);
        $this->assertStringContainsString('Seluruh hak cipta dilindungi.', $virtualAccountHtml);
        $this->assertStringContainsString('Hasil seleksi untuk calon peserta', $announcementHtml);
        $this->assertStringContainsString('Buka Dashboard SPMB', $announcementHtml);
        $this->assertStringNotContainsString('All rights reserved.', $virtualAccountHtml.$announcementHtml);
    }
}
