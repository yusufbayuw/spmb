<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\NotificationDeliveryCenter;
use App\Jobs\SendApplicantEmailChangeConfirmation;
use App\Jobs\SendApplicantPasswordResetMail;
use App\Jobs\SendRegistrationActionReminder;
use App\Models\AdmissionOffer;
use App\Models\MailDeliveryAttempt;
use App\Models\Payment;
use App\Models\PendingApplicantEmailChange;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantEmailCorrectionService;
use App\Services\RegistrationEmailDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationDeliveryManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        foreach (['super_admin', 'admin_unit', 'tu', 'pendaftar'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_admin_unit_can_request_password_reset_without_changing_password(): void
    {
        [$registration, $admin] = $this->scenario();
        $old = $registration->user->password;

        $attempt = app(RegistrationEmailDeliveryService::class)->resend(
            $registration, $admin, 'password_reset', 'Tidak menerima email reset'
        );

        $this->assertSame('password_reset', $attempt->type);
        $this->assertSame($old, $registration->user->fresh()->password);
        Queue::assertPushed(SendApplicantPasswordResetMail::class);
    }

    public function test_tu_and_other_unit_cannot_request_password_reset(): void
    {
        [$registration, $admin] = $this->scenario();
        $unit = Unit::create(['name' => 'Unit Lain', 'code' => 'MAIL-2', 'is_active' => true]);

        $service = app(RegistrationEmailDeliveryService::class);
        $this->assertFalse($service->canManage($this->staff($unit), $registration));
        $this->assertFalse($service->canManage($this->staff($registration->unit, 'tu'), $registration));
        $this->assertTrue($service->canManage($admin, $registration));
    }

    public function test_recovery_before_registration_requires_a_trusted_unit_or_super_admin(): void
    {
        $unit = Unit::create(['name' => 'Unit Satu', 'code' => 'MAIL-1', 'is_active' => true]);
        $other = Unit::create(['name' => 'Unit Lain', 'code' => 'MAIL-2', 'is_active' => true]);
        $applicant = $this->staff($unit, 'pendaftar');
        $service = app(RegistrationEmailDeliveryService::class);
        $admin = $this->staff($unit);

        $this->assertTrue($service->canRecoverAccount($admin, $applicant));
        $this->assertFalse($service->canRecoverAccount($this->staff($other), $applicant));
        $this->assertFalse($service->canRecoverAccount($this->staff($unit, 'tu'), $applicant));

        $attempt = $service->recoverAccount($applicant, $admin, 'password_reset', 'Akun belum mendaftar');
        $this->assertNull($attempt->registration_id);
        Queue::assertPushed(SendApplicantPasswordResetMail::class);

        $applicant->update(['unit_id' => null]);
        $this->assertFalse($service->canRecoverAccount($admin, $applicant->fresh()));
        $this->assertTrue($service->canRecoverAccount($this->staff($unit, 'super_admin'), $applicant->fresh()));
    }

    public function test_reminder_is_rechecked_when_queue_executes(): void
    {
        [$registration, $admin] = $this->scenario();
        $registration->update(['data_validation_status' => 'revision']);

        $service = app(RegistrationEmailDeliveryService::class);
        $this->assertArrayHasKey('revision_reminder', $service->availableTypes($registration->fresh()));
        $attempt = $service->resend($registration->fresh(), $admin, 'revision_reminder', 'Perbaiki data');

        $registration->update(['data_validation_status' => 'valid']);
        Mail::fake();

        (new SendRegistrationActionReminder($registration->id, $attempt->id))->handle($service);
        $this->assertSame('skipped', $attempt->fresh()->status);
        $this->assertSame(1, $attempt->fresh()->attempt_count);
        Mail::assertNothingSent();
    }

    public function test_payment_test_and_offer_reminders_are_only_available_at_relevant_stages(): void
    {
        [$registration] = $this->scenario();
        $service = app(RegistrationEmailDeliveryService::class);

        $registration->update(['current_stage' => 'payment']);
        Payment::create(['registration_id' => $registration->id, 'va_number' => '113366',
            'status' => 'pending', 'amount' => 100000]);
        $this->assertArrayHasKey('payment_reminder', $service->availableTypes($registration->fresh()));
        $registration->latestPayment->update(['proof_path' => 'payments/proof.pdf']);
        $this->assertArrayNotHasKey('payment_reminder', $service->availableTypes($registration->fresh()));

        $registration->update(['current_stage' => 'tests']);
        $this->assertArrayHasKey('test_reminder', $service->availableTypes($registration->fresh()));

        $registration->update(['current_stage' => 'admission_offer']);
        AdmissionOffer::create(['registration_id' => $registration->id,
            'status' => 'offered', 'offered_at' => now(),
            'expires_at' => now()->addDay()]);
        $this->assertArrayHasKey('offer_reminder', $service->availableTypes($registration->fresh()));
    }

    public function test_scheduled_reminders_are_opt_in_and_idempotent(): void
    {
        [$registration] = $this->scenario();
        $registration->update(['data_validation_status' => 'revision']);
        $service = app(RegistrationEmailDeliveryService::class);

        config()->set('spmb.mail.automatic_reminders_enabled', false);
        $this->artisan('spmb:remind-pending-actions', ['--execute' => true])->assertExitCode(0);
        $this->assertSame(0, MailDeliveryAttempt::count());

        config()->set('spmb.mail.automatic_reminders_enabled', true);
        $this->assertNotNull($service->scheduleReminder($registration->fresh(), 'revision_reminder'));
        $this->assertNull($service->scheduleReminder($registration->fresh(), 'revision_reminder'));
        $this->assertDatabaseCount('mail_delivery_attempts', 1);
        Queue::assertPushed(SendRegistrationActionReminder::class, 1);
    }

    public function test_email_correction_needs_matching_identity_and_applicant_approval(): void
    {
        [$registration, $admin] = $this->scenario();
        $service = app(ApplicantEmailCorrectionService::class);
        $user = $registration->user;
        $original = $user->email;

        try {
            $service->request($registration, $admin, 'new-owner@example.test',
                '0000000000000000', '2018-01-01', 'Identitas diperiksa');
            $this->fail('Identitas salah seharusnya ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nik', $e->errors());
        }
        $change = $service->request($registration, $admin, 'new-owner@example.test',
            '3273010101010001', '2018-01-01', 'Identitas diperiksa');
        Queue::assertPushed(SendApplicantEmailChangeConfirmation::class);
        $this->assertSame($original, $user->fresh()->email);
        $this->assertSame('pending', $change->status);

        $url = URL::temporarySignedRoute('applicant.email-change.confirm', $change->expires_at,
            ['emailChange' => $change->uuid], absolute: false);

        $this->actingAs($this->staff($registration->unit, 'pendaftar'))->get($url)->assertForbidden();
        $this->assertSame($original, $user->fresh()->email);

        $this->actingAs($user)->get($url)->assertRedirect('/pendaftar/profile');
        $this->assertSame('new-owner@example.test', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('confirmed', $change->fresh()->status);
        $this->actingAs($user)->get($url)->assertForbidden();
    }

    public function test_email_correction_rejects_cross_unit_and_expired_requests(): void
    {
        [$registration, $admin] = $this->scenario();
        $other = Unit::create(['name' => 'Unit Lain', 'code' => 'MAIL-3', 'is_active' => true]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ApplicantEmailCorrectionService::class)->request($registration, $this->staff($other),
            'external@example.test', '3273010101010001', '2018-01-01', 'Tidak berhak');
    }

    public function test_delivery_center_scopes_by_unit_and_rejects_tu(): void
    {
        [$registration, $admin] = $this->scenario();
        $other = Unit::create(['name' => 'Unit Dua', 'code' => 'MAIL-2', 'is_active' => true]);

        MailDeliveryAttempt::create(['unit_id' => $registration->unit_id, 'registration_id' => $registration->id,
            'type' => 'verification', 'origin' => 'manual', 'status' => 'failed',
            'source_id' => $registration->user_id, 'recipient_email' => 'owner@example.test']);
        MailDeliveryAttempt::create(['unit_id' => $other->id,
            'type' => 'verification', 'origin' => 'manual', 'status' => 'failed',
            'source_id' => 999, 'recipient_email' => 'different-unit@example.test']);

        $this->actingAs($admin)->get('/admin/notification-delivery-center')
            ->assertOk()->assertSee('owner@example.test')->assertDontSee('different-unit@example.test');

        $this->actingAs($this->staff($other, 'tu'))
            ->get('/admin/notification-delivery-center')->assertForbidden();
    }

    private function scenario(): array
    {
        $unit = Unit::create(['name' => 'Unit Surat', 'code' => 'MAIL-UNIT', 'is_active' => true]);
        $admin = $this->staff($unit);
        $opening = RegistrationOpening::create(['unit_id' => $unit->id,
            'academic_year' => '2026/2027', 'wave' => 'Gelombang 1',
            'registration_fee' => 100000, 'status' => 'draft']);
        $applicant = $this->staff($unit, 'pendaftar');
        $registration = Registration::create([
            'user_id' => $applicant->id, 'unit_id' => $unit->id, 'registration_opening_id' => $opening->id,
            'nik' => '3273010101010001', 'full_name' => 'Calon Siswa',
            'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2018-01-01',
            'home_address' => 'Bandung', 'status' => 'submitted', 'current_stage' => 'data_validation',
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
