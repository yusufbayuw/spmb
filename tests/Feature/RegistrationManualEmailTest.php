<?php

namespace Tests\Feature;

use App\Jobs\SendRegistrationManualEmail;
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
