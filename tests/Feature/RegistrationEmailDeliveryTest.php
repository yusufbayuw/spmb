<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncementPublishedMail;
use App\Jobs\SendApplicantVerificationMail;
use App\Jobs\SendVirtualAccountMail;
use App\Models\Announcement;
use App\Models\MailDeliveryAttempt;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\RegistrationEmailDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        foreach (['admin_unit', 'tu', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_admin_unit_can_resend_verification_only_for_unverified_applicant_in_own_unit(): void
    {
        [$registration, $admin] = $this->scenario();
        $registration->user->forceFill(['email_verified_at' => null])->save();

        $attempt = app(RegistrationEmailDeliveryService::class)->resend(
            $registration, $admin, 'verification', 'Email belum diterima'
        );

        $this->assertSame('queued', $attempt->status);
        $this->assertSame('verification', $attempt->type);
        $this->assertSame('manual', $attempt->origin);
        $this->assertDatabaseHas('audit_logs', ['event' => 'mail.resend_requested']);
        Queue::assertPushed(SendApplicantVerificationMail::class);

        $otherUnit = Unit::create(['name' => 'Other Unit', 'code' => 'MAIL-OTHER', 'is_active' => true]);
        $otherAdmin = $this->staff($otherUnit);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(RegistrationEmailDeliveryService::class)->resend($registration, $otherAdmin, 'verification', 'Other unit');
    }

    public function test_tu_cannot_resend_and_verified_address_is_not_eligible(): void
    {
        [$registration, $admin] = $this->scenario();
        $registration->user->forceFill(['email_verified_at' => now()])->save();

        $this->assertArrayNotHasKey('verification', app(RegistrationEmailDeliveryService::class)->availableTypes($registration->fresh()));
        $tu = $this->staff($registration->unit, 'tu');
        $this->assertFalse(app(RegistrationEmailDeliveryService::class)->canManage($tu, $registration));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(RegistrationEmailDeliveryService::class)->resend($registration, $tu, 'verification', 'Coba');
    }

    public function test_virtual_account_resend_never_assigns_new_va_or_changes_payment(): void
    {
        [$registration, $admin] = $this->scenario();
        $registration->forceFill(['current_stage' => 'payment'])->save();
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'va_number' => '991100002233',
            'amount' => 100000,
            'status' => 'pending',
        ]);

        $attempt = app(RegistrationEmailDeliveryService::class)->resend(
            $registration->fresh(), $admin, 'virtual_account', 'Email VA tidak masuk'
        );

        $this->assertSame($payment->id, $attempt->source_id);
        $this->assertSame(1, Payment::count());
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('payment', $registration->fresh()->current_stage);
        Queue::assertPushed(SendVirtualAccountMail::class);
    }

    public function test_unpublished_announcement_cannot_be_resent(): void
    {
        [$registration, $admin] = $this->scenario();
        Announcement::create(['registration_id' => $registration->id, 'status' => 'draft']);
        $this->expectException(ValidationException::class);
        app(RegistrationEmailDeliveryService::class)->resend($registration, $admin, 'announcement', 'Ulang');
    }

    public function test_published_announcement_can_be_resent_without_republishing(): void
    {
        [$registration, $admin] = $this->scenario();
        $announcement = Announcement::create([
            'registration_id' => $registration->id,
            'status' => 'published',
            'title' => 'Hasil SPMB',
            'published_at' => now(),
        ]);

        $attempt = app(RegistrationEmailDeliveryService::class)->resend(
            $registration, $admin, 'announcement', 'Email hasil tidak diterima'
        );

        $this->assertSame($announcement->id, $attempt->source_id);
        $this->assertSame('published', $announcement->fresh()->status);
        Queue::assertPushed(SendAnnouncementPublishedMail::class);
    }

    public function test_pending_mail_prevents_duplicate_queue_requests(): void
    {
        [$registration, $admin] = $this->scenario();
        $registration->user->forceFill(['email_verified_at' => null])->save();
        $service = app(RegistrationEmailDeliveryService::class);
        $service->resend($registration, $admin, 'verification', 'Permintaan pertama');

        $this->expectException(ValidationException::class);
        $service->resend($registration, $admin, 'verification', 'Permintaan kedua');
    }

    public function test_quota_limits_manual_resends_even_after_cooldown(): void
    {
        [$registration, $admin] = $this->scenario();
        $registration->user->forceFill(['email_verified_at' => null])->save();

        for ($i = 0; $i < 5; $i++) {
            MailDeliveryAttempt::create([
                'registration_id' => $registration->id,
                'unit_id' => $registration->unit_id,
                'type' => 'verification',
                'origin' => 'manual',
                'status' => 'sent',
                'source_id' => $registration->user_id,
                'recipient_email' => $registration->user->email,
                'created_at' => now()->subHours(3 + $i),
            ]);
        }

        $this->expectException(ValidationException::class);
        app(RegistrationEmailDeliveryService::class)->resend($registration, $admin, 'verification', 'Keenam');
    }

    public function test_auto_send_creates_history_row_with_no_staff_approval(): void
    {
        [$registration] = $this->scenario();
        $payment = Payment::create([
            'registration_id' => $registration->id, 'va_number' => '9988',
            'amount' => 100000, 'status' => 'pending',
        ]);

        app(RegistrationEmailDeliveryService::class)->queueAutomaticVirtualAccount($payment);

        $this->assertDatabaseHas('mail_delivery_attempts', [
            'registration_id' => $registration->id,
            'type' => 'virtual_account',
            'origin' => 'automatic',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SendVirtualAccountMail::class);
    }

    private function scenario(): array
    {
        $unit = Unit::create(['name' => 'Mail Unit', 'code' => 'MAIL-UNIT', 'is_active' => true]);
        $admin = $this->staff($unit);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1', 'registration_fee' => 100000, 'status' => 'draft',
        ]);
        $applicant = User::factory()->create(['email_verified_at' => null, 'is_active' => true]);
        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'nik' => '3273010101010001',
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2018-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
        ]);

        return [$registration, $admin];
    }

    private function staff(Unit $unit, string $role = 'admin_unit'): User
    {
        $staff = User::factory()->create(['unit_id' => $unit->id, 'role' => $role, 'is_active' => true]);
        $staff->assignRole($role);

        return $staff;
    }
}
