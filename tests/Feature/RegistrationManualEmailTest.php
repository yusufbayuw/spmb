<?php

namespace Tests\Feature;

use App\Jobs\SendRegistrationManualEmail;
use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\RegistrationManualEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\RegistrationManualEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RegistrationManualEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        foreach (['tu', 'admin_unit', 'super_admin', 'pendaftar'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_own_unit_staff_can_queue_manual_message_without_changing_registration(): void
    {
        [$registration, $staff] = $this->scenario();

        $attempt = app(RegistrationManualEmailService::class)->send(
            $registration, $staff, 'Informasi Seleksi', 'Mohon periksa informasi di portal.', 'Informasi tambahan'
        );

        $this->assertSame('queued', $attempt->status);
        $this->assertSame('manual_message', $attempt->type);
        $this->assertSame($registration->user->email, $attempt->recipient_email);
        $this->assertDatabaseHas('registration_manual_emails', [
            'mail_delivery_attempt_id' => $attempt->id,
            'subject' => 'Informasi Seleksi',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'mail.manual_message_requested']);
        $this->assertSame('data_validation', $registration->fresh()->current_stage);
        Queue::assertPushed(SendRegistrationManualEmail::class);
    }

    public function test_cross_unit_staff_cannot_queue_or_read_as_applicant(): void
    {
        [$registration, $staff] = $this->scenario();
        $other = Unit::create(['name' => 'Unit B', 'code' => 'MAIL-B', 'is_active' => true]);
        $staff->update(['unit_id' => $other->id]);
        $service = app(RegistrationManualEmailService::class);
        $this->assertFalse($service->canCompose($staff, $registration));
        try {
            $service->send($registration, $staff, 'Subjek', 'Isi pesan', 'Alasan');
            $this->fail('Cross-unit email must be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, \App\Models\MailDeliveryAttempt::count());
    }

    public function test_queued_message_is_skipped_if_destination_changes(): void
    {
        [$registration, $staff] = $this->scenario();
        $attempt = app(RegistrationManualEmailService::class)->send(
            $registration, $staff, 'Subjek', 'Isi pesan', 'Alasan'
        );
        $registration->user->forceFill(['email' => 'updated@example.test'])->save();
        (new SendRegistrationManualEmail($attempt->id))->handle(
            app(\App\Services\RegistrationEmailDeliveryService::class),
            app(RegistrationManualEmailService::class),
        );

        $this->assertSame('skipped', $attempt->fresh()->status);
    }

    public function test_pending_message_blocks_replay(): void
    {
        [$registration, $staff] = $this->scenario();
        $service = app(RegistrationManualEmailService::class);
        $service->send($registration, $staff, 'Pertama', 'Isi pesan pertama', 'Alasan');

        $this->expectException(ValidationException::class);
        $service->send($registration, $staff, 'Kedua', 'Isi pesan kedua', 'Alasan');
    }

    public function test_rich_manual_message_is_delivered_as_html(): void
    {
        [$registration, $staff] = $this->scenario();
        $service = app(RegistrationManualEmailService::class);
        $attempt = $service->send(
            $registration, $staff, 'Tes Rich Email',
            '<p><strong>Informasi</strong> <em>Penting</em>.</p>',
            'Informasi tambahan',
        );

        Mail::shouldReceive('html')->once()->withArgs(
            fn (string $html, $callback): bool =>
                str_contains($html, '<strong>Informasi</strong>')
                && str_contains($html, '<em>Penting</em>'),
        );

        (new SendRegistrationManualEmail($attempt->id))->handle(
            app(\App\Services\RegistrationEmailDeliveryService::class), $service,
        );

        $this->assertSame('sent', $attempt->fresh()->status);
    }

    public function test_lms_access_message_is_encrypted_then_scrubbed_after_delivery(): void
    {
        [$registration, $staff] = $this->scenario();
        $test = AdmissionTest::create([
            'unit_id' => $registration->unit_id, 'name' => 'Tes Akses',
        ]);
        $result = AdmissionTestResult::create([
            'registration_id' => $registration->id, 'admission_test_id' => $test->id,
            'status' => 'scheduled', 'result' => 'pending',
        ]);
        $service = app(RegistrationManualEmailService::class);
        $attempt = $service->sendLmsAccess($result, $staff, [
            'platform' => 'Moodle',
            'login_url' => 'https://example.test/lms',
            'username' => 'sample.account',
            'temporary_password' => 'DemoKey123',
            'subject' => 'Akses Moodle',
            'body' => '<p>Silakan <strong>login</strong>.</p>',
            'reason' => 'Ujian daring',
        ]);

        $this->assertSame('lms_access', $attempt->type);
        $this->assertDatabaseHas('audit_logs', ['event' => 'mail.lms_access_requested']);
        $raw = DB::table('registration_manual_emails')
            ->where('mail_delivery_attempt_id', $attempt->id)->value('body');
        $this->assertStringNotContainsString('DemoKey123', $raw);
        $this->assertSame('data_validation', $registration->fresh()->current_stage);

        Mail::shouldReceive('html')->once()->withArgs(
            fn (string $html, $callback): bool =>
                str_contains($html, '<strong>login</strong>')
                && str_contains($html, 'sample.account')
                && str_contains($html, 'DemoKey123'),
        );

        (new SendRegistrationManualEmail($attempt->id))->handle(
            app(\App\Services\RegistrationEmailDeliveryService::class), $service,
        );

        $this->assertSame('sent', $attempt->fresh()->status);
        $remaining = RegistrationManualEmail::query()
            ->where('mail_delivery_attempt_id', $attempt->id)->firstOrFail()->body;
        $this->assertStringNotContainsString('DemoKey123', $remaining);
    }

    public function test_lms_credentials_require_a_verified_applicant_address(): void
    {
        [$registration, $staff] = $this->scenario();
        $registration->user->forceFill(['email_verified_at' => null])->save();

        $admissionTest = AdmissionTest::create([
            'unit_id' => $registration->unit_id, 'name' => 'Tes Moodle',
        ]);
        $result = AdmissionTestResult::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $admissionTest->id,
            'status' => 'scheduled', 'result' => 'pending',
        ]);
        $service = app(RegistrationManualEmailService::class);

        $this->assertFalse($service->canSendLmsAccess($staff, $result));

        try {
            $service->sendLmsAccess($result, $staff, [
                'platform' => 'Moodle',
                'login_url' => 'https://lms.example.test/login',
                'username' => 'user-test',
                'subject' => 'Akses',
                'body' => '<p>Informasi akun</p>',
                'reason' => 'Tes daring',
            ]);
            $this->fail('LMS access was sent to an unverified email.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('mail_delivery_attempts', 0);
    }

    public function test_stale_queued_lms_credentials_are_skipped_and_scrubbed(): void
    {
        [$registration, $staff] = $this->scenario();
        $admissionTest = AdmissionTest::create([
            'unit_id' => $registration->unit_id, 'name' => 'Tes Moodle',
        ]);
        $result = AdmissionTestResult::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $admissionTest->id,
            'status' => 'scheduled', 'result' => 'pending',
        ]);
        $service = app(RegistrationManualEmailService::class);
        $attempt = $service->sendLmsAccess($result, $staff, [
            'platform' => 'Moodle',
            'login_url' => 'https://lms.example.test/login',
            'username' => 'peserta-test',
            'temporary_password' => 'demo-temporary-secret',
            'subject' => 'Akses',
            'body' => '<p>Informasi akun</p>',
            'reason' => 'Tes daring',
        ]);
        $attempt->forceFill(['created_at' => now()->subHours(3)])->save();

        Mail::shouldReceive('html')->never();
        (new SendRegistrationManualEmail($attempt->id))->handle(
            app(\App\Services\RegistrationEmailDeliveryService::class), $service,
        );

        $this->assertSame('skipped', $attempt->fresh()->status);
        $this->assertStringNotContainsString(
            'demo-temporary-secret',
            RegistrationManualEmail::query()
                ->where('mail_delivery_attempt_id', $attempt->id)
                ->firstOrFail()
                ->body,
        );
    }

    public function test_lms_credentials_are_not_sent_if_email_verification_is_revoked(): void
    {
        [$registration, $staff] = $this->scenario();
        $admissionTest = AdmissionTest::create([
            'unit_id' => $registration->unit_id, 'name' => 'Tes Moodle',
        ]);
        $result = AdmissionTestResult::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $admissionTest->id,
            'status' => 'scheduled', 'result' => 'pending',
        ]);
        $service = app(RegistrationManualEmailService::class);
        $attempt = $service->sendLmsAccess($result, $staff, [
            'platform' => 'Moodle',
            'login_url' => 'https://lms.example.test/login',
            'username' => 'peserta-test',
            'temporary_password' => 'demo-temporary-secret',
            'subject' => 'Akses',
            'body' => '<p>Informasi akun</p>',
            'reason' => 'Tes daring',
        ]);
        $registration->user->forceFill(['email_verified_at' => null])->save();

        Mail::shouldReceive('html')->never();
        (new SendRegistrationManualEmail($attempt->id))->handle(
            app(\App\Services\RegistrationEmailDeliveryService::class), $service,
        );

        $this->assertSame('skipped', $attempt->fresh()->status);
        $this->assertStringNotContainsString(
            'demo-temporary-secret',
            RegistrationManualEmail::query()
                ->where('mail_delivery_attempt_id', $attempt->id)
                ->firstOrFail()
                ->body,
        );
    }

    /** @return array{Registration, User} */
    private function scenario(): array
    {
        $unit = Unit::create(['name' => 'Email Unit', 'code' => 'MAIL-A', 'is_active' => true]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1', 'registration_fee' => 0, 'status' => 'draft',
        ]);
        $applicant = User::factory()->create([
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        $applicant->assignRole('pendaftar');

        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'nik' => '3273010101010049',
            'full_name' => 'Peserta Email',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2018-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
        ]);

        $staff = User::factory()->create([
            'unit_id' => $unit->id, 'role' => 'tu', 'is_active' => true,
        ]);
        $staff->assignRole('tu');

        return [$registration, $staff];
    }
}
