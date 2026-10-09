<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\ApplicantFileStorage;
use App\Services\RegistrationPurgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RegistrationPermanentPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unit_permission_is_disabled_by_default_and_scoped_per_unit(): void
    {
        [$registration, $unit] = $this->registrationFixture('OWN');
        [$foreign, $otherUnit] = $this->registrationFixture('OTH');
        $adminUnit = $this->staff('admin_unit', $unit);

        $service = app(RegistrationPurgeService::class);

        $this->assertFalse($service->canPurge($adminUnit, $registration));
        $this->assertTrue($service->canPurge($this->staff('super_admin', $unit), $registration));

        $unit->update(['allow_admin_unit_registration_purge' => true]);
        $this->assertTrue($service->canPurge($adminUnit, $registration));
        $this->assertFalse($service->canPurge($adminUnit, $foreign));
        $this->assertFalse($service->canPurge($this->staff('tu', $unit), $registration));

        $adminUnit->update(['is_active' => false]);
        $this->assertFalse($service->canPurge($adminUnit->fresh(), $registration));
    }

    public function test_preview_lists_restrictive_children_and_preserved_shared_dependencies(): void
    {
        [$registration, $unit] = $this->registrationFixture('PRE');
        $service = app(RegistrationPurgeService::class);

        $this->addPaymentAndReceipt($registration);
        Document::create([
            'registration_id' => $registration->id,
            'type' => 'other',
            'file_path' => 'documents/'.$registration->id.'/proof.pdf',
            'original_name' => 'proof.pdf',
            'file_type' => 'pdf',
            'file_size' => 1024,
            'is_verified' => false,
        ]);

        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        Storage::fake(ApplicantFileStorage::LEGACY_PUBLIC_DISK);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)
            ->put('documents/'.$registration->id.'/proof.pdf', 'test-file');

        $preview = $service->preview($registration, $this->staff('super_admin', $unit));

        $this->assertSame(1, $preview['counts']['documents']['count']);
        $this->assertSame(1, $preview['counts']['payments']['count']);
        $this->assertSame(1, $preview['counts']['payment_receipts']['count']);
        $this->assertSame(1, $preview['virtual_accounts']);
        $this->assertCount(1, $preview['files']);
    }

    public function test_purge_removes_dependent_data_and_files_but_preserves_account_pool_and_audit(): void
    {
        [$registration, $unit] = $this->registrationFixture('DEL');
        $actor = $this->staff('super_admin', $unit);
        $applicantId = $registration->user_id;
        $va = $this->addPaymentAndReceipt($registration);
        $second = Registration::create([
            'user_id' => $applicantId,
            'unit_id' => $unit->id,
            'registration_opening_id' => $registration->registration_opening_id,
            'nik' => '3273010101010098',
            'full_name' => 'Other Child Same Account',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2015-01-01',
            'home_address' => 'Bandung',
        ]);

        Storage::fake('local');
        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        Storage::fake(ApplicantFileStorage::LEGACY_PUBLIC_DISK);

        $file = 'documents/'.$registration->id.'/photo.png';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($file, 'image');
        $otherFile = 'documents/'.$second->id.'/photo.png';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($otherFile, 'other-image');
        $sharedFile = 'pre-registration/'.$applicantId.'/achievements/shared.png';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($sharedFile, 'shared');

        app(\App\Services\AuditTrail::class)->record(
            event: 'registration.test_before_purge',
            subject: $registration,
            actor: $actor,
        );
        $service = app(RegistrationPurgeService::class);
        $preview = $service->preview($registration, $actor);

        $result = $service->purge(
            $registration, $actor, $preview['fingerprint'],
            'HAPUS-'.$registration->uuid, 'Duplikasi pendaftaran peserta',
        );

        $this->assertFalse($result['cleanup_pending']);
        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('payments', ['registration_id' => $registration->id]);
        $this->assertDatabaseMissing('payment_receipts', ['number' => 'REC-'. $registration->id]);
        $this->assertDatabaseHas('users', ['id' => $applicantId]);
        $this->assertDatabaseHas('registrations', ['id' => $second->id]);
        $this->assertDatabaseHas('virtual_accounts', [
            'id' => $va->id, 'status' => 'cancelled', 'registration_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'registration.permanently_purged']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'registration.test_before_purge']);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertMissing($file);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertExists($otherFile);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertExists($sharedFile);
        $this->assertTrue(Storage::disk('local')->exists($result['manifest']));
    }

    public function test_purge_refuses_confirmation_mismatch_without_database_changes(): void
    {
        [$registration, $unit] = $this->registrationFixture('DEN');
        $actor = $this->staff('super_admin', $unit);
        $preview = app(RegistrationPurgeService::class)->preview($registration, $actor);

        try {
            app(RegistrationPurgeService::class)->purge(
                $registration, $actor, $preview['fingerprint'],
                'HAPUS-WRONG', 'Alasan sudah cukup panjang',
            );
            $this->fail('Mismatch confirmation must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('confirmation', $e->errors());
        }

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    public function test_admin_unit_is_rechecked_against_toggle_at_execution_time(): void
    {
        [$registration, $unit] = $this->registrationFixture('CHG');
        $unit->update(['allow_admin_unit_registration_purge' => true]);
        $actor = $this->staff('admin_unit', $unit);
        $preview = app(RegistrationPurgeService::class)->preview($registration, $actor);
        $unit->update(['allow_admin_unit_registration_purge' => false]);

        try {
            app(RegistrationPurgeService::class)->purge(
                $registration, $actor, $preview['fingerprint'],
                'HAPUS-'.$registration->uuid, 'Permintaan pembatalan lengkap',
            );
            $this->fail('Turning off the setting must revoke purge immediately.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    private function addPaymentAndReceipt(Registration $registration): VirtualAccount
    {
        $va = VirtualAccount::create([
            'unit_id' => $registration->unit_id,
            'bank' => 'MANDIRI',
            'va_number' => '88331'.$registration->id,
            'status' => 'paid',
            'registration_id' => $registration->id,
        ]);
        $payment = Payment::create([
            'registration_id' => $registration->id,
            'virtual_account_id' => $va->id,
            'va_number' => $va->va_number,
            'amount' => 100000,
            'status' => 'verified',
        ]);
        PaymentReceipt::create([
            'payment_id' => $payment->id,
            'number' => 'REC-'.$registration->id,
            'details' => ['amount' => 100000],
            'issued_at' => now(),
        ]);

        return $va;
    }

    private function staff(string $role, Unit $unit): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => $role, 'unit_id' => $unit->id, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function registrationFixture(string $code): array
    {
        $unit = Unit::create(['name' => 'School '.$code, 'code' => $code, 'is_active' => true]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1', 'status' => 'open',
        ]);
        $registration = Registration::create([
            'user_id' => User::factory()->create()->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'nik' => '3273010101010044',
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2015-01-01',
            'home_address' => 'Bandung',
        ]);

        return [$registration, $unit];
    }
}
