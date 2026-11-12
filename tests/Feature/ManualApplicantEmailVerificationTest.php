<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\Unit;
use App\Models\User;
use App\Services\ManualApplicantEmailVerificationService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ManualApplicantEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin_unit', 'tu', 'pendaftar'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_super_admin_verifies_one_account_and_writes_a_complete_audit_without_changing_registration_or_payment(): void
    {
        $admin = $this->userWithRole('super_admin');
        $applicant = $this->userWithRole('pendaftar', ['email_verified_at' => null]);
        $unit = Unit::create(['name' => 'SMA Verifikasi', 'code' => 'SMA-VERIFY', 'is_active' => true]);
        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'nik' => '3273010101010001',
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2018-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'payment',
        ]);
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'va_number' => '998877665544',
            'amount' => 100000,
            'status' => 'pending',
        ]);
        $registrationBefore = $registration->fresh()->getAttributes();
        $paymentBefore = $payment->fresh()->getAttributes();

        $this->actingAs($admin);
        app(ManualApplicantEmailVerificationService::class)->verify($admin, $applicant, $this->inputFor($applicant));

        $this->assertTrue($applicant->fresh()->hasVerifiedEmail());
        $this->assertSame($registrationBefore, $registration->fresh()->getAttributes());
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());

        $log = AuditLog::query()->where('event', 'applicant.email_verified_manually')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($applicant->id, $log->auditable_id);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertSame($applicant->unit_id, $log->unit_id);
        $this->assertFalse($log->old_values['email_verified']);
        $this->assertTrue($log->new_values['email_verified']);
        $this->assertSame('in_person', $log->metadata['verification_method']);
        $this->assertSame('TICKET-2026-01', $log->metadata['evidence_reference']);
        $this->assertSame('Tautan email tidak sampai, identitas sudah diperiksa.', $log->metadata['reason']);
        $this->assertTrue($log->metadata['identity_checked']);
        $this->assertNotNull($log->created_at);
    }

    public function test_only_active_super_admin_can_verify_even_when_service_called_directly(): void
    {
        $target = $this->userWithRole('pendaftar', ['email_verified_at' => null]);

        foreach (['admin_unit', 'tu', 'pendaftar'] as $role) {
            $actor = $this->userWithRole($role);
            try {
                app(ManualApplicantEmailVerificationService::class)->verify($actor, $target, $this->inputFor($target));
                $this->fail('The '.$role.' role should not be able to verify email manually.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $inactiveSuper = $this->userWithRole('super_admin', ['is_active' => false]);
        try {
            app(ManualApplicantEmailVerificationService::class)->verify($inactiveSuper, $target, $this->inputFor($target));
            $this->fail('An inactive super admin must not perform manual verification.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertFalse($target->fresh()->hasVerifiedEmail());
        $this->assertDatabaseMissing('audit_logs', ['event' => 'applicant.email_verified_manually']);
    }

    public function test_requires_correct_email_attestation_evidence_reference_and_reason(): void
    {
        $admin = $this->userWithRole('super_admin');
        $target = $this->userWithRole('pendaftar', ['email_verified_at' => null]);
        $valid = $this->inputFor($target);

        $invalidInputs = [
            ['confirmed_email' => 'someone-else@example.test'],
            ['identity_checked' => false],
            ['evidence_reference' => ''],
            ['verification_method' => 'unrecognized'],
            ['reason' => ''],
        ];

        foreach ($invalidInputs as $changed) {
            try {
                app(ManualApplicantEmailVerificationService::class)->verify($admin, $target, array_merge($valid, $changed));
                $this->fail('Invalid manual verification input was accepted.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        $this->assertFalse($target->fresh()->hasVerifiedEmail());
        $this->assertDatabaseMissing('audit_logs', ['event' => 'applicant.email_verified_manually']);
    }

    public function test_cannot_verify_inactive_privileged_or_already_verified_accounts(): void
    {
        $admin = $this->userWithRole('super_admin');
        $inactive = $this->userWithRole('pendaftar', ['is_active' => false, 'email_verified_at' => null]);
        $privileged = $this->userWithRole('admin_unit', ['email_verified_at' => null]);
        $verified = $this->userWithRole('pendaftar');

        foreach ([$inactive, $privileged] as $target) {
            try {
                app(ManualApplicantEmailVerificationService::class)->verify($admin, $target, $this->inputFor($target));
                $this->fail('Ineligible user was accepted.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->expectException(ValidationException::class);
        app(ManualApplicantEmailVerificationService::class)->verify($admin, $verified, $this->inputFor($verified));
    }

    public function test_repeated_request_does_not_create_another_manual_verification_audit(): void
    {
        $admin = $this->userWithRole('super_admin');
        $applicant = $this->userWithRole('pendaftar', ['email_verified_at' => null]);
        $service = app(ManualApplicantEmailVerificationService::class);

        $service->verify($admin, $applicant, $this->inputFor($applicant));

        try {
            $service->verify($admin, $applicant, $this->inputFor($applicant));
            $this->fail('Repeated manual verification should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('confirmed_email', $e->errors());
        }

        $this->assertSame(1, AuditLog::query()->where('event', 'applicant.email_verified_manually')->count());
    }

    public function test_super_admin_can_use_the_users_table_action(): void
    {
        $this->seed(ShieldSeeder::class);

        $admin = $this->userWithRole('super_admin');
        $applicant = $this->userWithRole('pendaftar', ['email_verified_at' => null]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListUsers::class)
            ->callTableAction('manualVerifyApplicantEmail', $applicant, $this->inputFor($applicant))
            ->assertHasNoTableActionErrors();

        $this->assertTrue($applicant->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'applicant.email_verified_manually',
            'user_id' => $admin->id,
            'auditable_id' => $applicant->id,
        ]);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function inputFor(User $target): array
    {
        return [
            'confirmed_email' => $target->email,
            'verification_method' => 'in_person',
            'evidence_reference' => 'TICKET-2026-01',
            'reason' => 'Tautan email tidak sampai, identitas sudah diperiksa.',
            'identity_checked' => true,
        ];
    }
}
