<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\PaymentReceipt;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Payment;
use App\Models\VirtualAccount;
use App\Models\Unit;
use App\Models\User;
use App\Services\ControlledDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlledDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_deletion_permissions_default_off_and_tu_never_inherits_them(): void
    {
        $unit = Unit::create(['name' => 'Unit A', 'code' => 'TEST-DLT', 'is_active' => true]);
        Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        $adminUnit = User::factory()->create(['unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true]);
        $adminUnit->assignRole('admin_unit');
        $tu = User::factory()->create(['unit_id' => $unit->id, 'role' => 'tu', 'is_active' => true]);
        $tu->assignRole('tu');

        $service = app(ControlledDeletionService::class);
        $this->assertFalse($service->allowed($adminUnit, $unit, 'registration'));
        $this->assertFalse($service->allowed($adminUnit, $unit, 'virtual_account'));
        $this->assertFalse($service->allowed($adminUnit, $unit, 'opening'));
        $unit->update(['allow_admin_unit_registration_deletion' => true]);
        $this->assertTrue($service->allowed($adminUnit, $unit->fresh(), 'registration'));
        $this->assertFalse($service->allowed($tu, $unit->fresh(), 'registration'));
    }
    public function test_admin_unit_deletes_uncommitted_registration_but_preserves_applicant_account(): void
    {
        $unit = Unit::create(['name' => 'Safe Delete', 'code' => 'SAFE-REG', 'is_active' => true, 'allow_admin_unit_registration_deletion' => true]);
        $staff = $this->staff($unit);
        $registration = $this->registration($unit);
        $applicantId = $registration->user_id;

        app(ControlledDeletionService::class)->deleteRegistration($registration, $staff, 'Duplikat pendaftaran', 'HAPUS');

        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('users', ['id' => $applicantId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'registration.permanently_deleted']);
    }

    public function test_unused_pending_payment_and_assigned_va_are_cleaned_in_one_delete(): void
    {
        $unit = Unit::create(['name' => 'Paid Unit', 'code' => 'PAID-REG', 'is_active' => true, 'allow_admin_unit_registration_deletion' => true]);
        $staff = $this->staff($unit);
        $registration = $this->registration($unit);
        $registration->update([
            'registration_number' => 'PAID-REG-2026-0001',
            'applicant_card_issued_at' => now(),
            'current_stage' => 'applicant_card',
        ]);
        $applicantId = $registration->user_id;

        $va = VirtualAccount::create([
            'unit_id' => $unit->id, 'bank' => 'BANK', 'va_number' => 'PAID-REG-VA01',
            'registration_id' => $registration->id, 'status' => 'assigned',
            'assigned_at' => now()->subHour(), 'assigned_by' => $staff->id,
        ]);
        $assignedAt = $va->assigned_at->toDateTimeString();

        Payment::create([
            'registration_id' => $registration->id,
            'virtual_account_id' => $va->id,
            'status' => 'pending', 'amount' => 100000,
        ]);

        $impact = app(ControlledDeletionService::class)->registrationImpact($registration);
        $this->assertStringContainsString('dapat dihapus', $impact);

        app(ControlledDeletionService::class)
            ->deleteRegistration($registration, $staff, 'Bersihkan data uji', 'HAPUS');

        $this->assertDatabaseMissing('registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('payments', ['registration_id' => $registration->id]);
        $this->assertDatabaseHas('virtual_accounts', [
            'id' => $va->id, 'status' => 'cancelled', 'registration_id' => null,
        ]);
        $this->assertSame($assignedAt, $va->fresh()->assigned_at->toDateTimeString());
        $this->assertDatabaseHas('users', ['id' => $applicantId]);
        foreach ([
            'payment.unused_pending_removed',
            'virtual_account.retired_after_registration_deletion',
            'registration.permanently_deleted',
        ] as $event) {
            $this->assertDatabaseHas('audit_logs', ['event' => $event]);
        }
    }

    public function test_pending_payment_with_uploaded_proof_still_blocks_deletion(): void
    {
        [$registration, $staff] = $this->setupProtectedRegistration();

        Payment::create([
            'registration_id' => $registration->id, 'status' => 'pending',
            'amount' => 100000, 'proof_path' => 'receipts/test.png',
            'proof_uploaded_at' => now(),
        ]);

        $this->assertProtectedDeletion($registration, $staff, 'bukti');
    }

    public function test_verified_payment_still_blocks_deletion(): void
    {
        [$registration, $staff] = $this->setupProtectedRegistration();

        Payment::create([
            'registration_id' => $registration->id, 'status' => 'verified',
            'amount' => 100000, 'verified_at' => now(),
        ]);

        $this->assertProtectedDeletion($registration, $staff, 'Pembayaran');
    }

    public function test_issued_receipt_blocks_even_an_unverified_pending_payment(): void
    {
        [$registration, $staff] = $this->setupProtectedRegistration();

        $payment = Payment::create([
            'registration_id' => $registration->id, 'status' => 'pending', 'amount' => 100000,
        ]);
        PaymentReceipt::create([
            'payment_id' => $payment->id,
            'number' => 'REC-DELETION-001',
            'details' => ['test' => true],
            'issued_at' => now(),
        ]);

        $this->assertProtectedDeletion($registration, $staff, 'Kuitansi');
    }

    public function test_legacy_transfer_proof_document_blocks_deletion(): void
    {
        [$registration, $staff] = $this->setupProtectedRegistration();

        Document::create([
            'registration_id' => $registration->id,
            'type' => 'payment_proof', 'file_path' => 'receipts/old.pdf',
            'original_name' => 'old.pdf', 'file_type' => 'pdf', 'file_size' => 10,
        ]);

        $this->assertProtectedDeletion($registration, $staff, 'Bukti transfer');
    }

    public function test_paid_va_blocks_deletion_even_if_payment_is_pending(): void
    {
        [$registration, $staff] = $this->setupProtectedRegistration();

        $va = VirtualAccount::create([
            'unit_id' => $registration->unit_id, 'bank' => 'BANK',
            'va_number' => 'PAID-VA-BLOCK', 'status' => 'paid',
            'registration_id' => $registration->id, 'assigned_at' => now(),
        ]);
        Payment::create([
            'registration_id' => $registration->id,
            'virtual_account_id' => $va->id,
            'status' => 'pending', 'amount' => 100000,
        ]);

        $this->assertProtectedDeletion($registration, $staff, 'Status VA');
        $this->assertDatabaseHas('virtual_accounts', [
            'id' => $va->id, 'status' => 'paid', 'registration_id' => $registration->id,
        ]);
    }

    public function test_formal_admission_decision_blocks_deletion(): void
    {
        [$registration, $staff] = $this->setupProtectedRegistration();
        $registration->update(['status' => 'accepted', 'accepted_at' => now()]);

        $this->assertProtectedDeletion($registration, $staff, 'keputusan resmi');
    }

    public function test_unauthorized_unit_admin_cannot_clean_pending_billing(): void
    {
        [$registration, $staff] = $this->setupProtectedRegistration();

        $other = Unit::create([
            'name' => 'Unit Other', 'code' => 'OTHER-DEL',
            'is_active' => true, 'allow_admin_unit_registration_deletion' => true,
        ]);
        $otherStaff = $this->staff($other);

        Payment::create([
            'registration_id' => $registration->id,
            'status' => 'pending', 'amount' => 100000,
        ]);

        try {
            app(ControlledDeletionService::class)
                ->deleteRegistration($registration, $otherStaff, 'Lintas unit', 'HAPUS');
            $this->fail('Another unit must never be able to delete this registration.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('payments', ['registration_id' => $registration->id]);
    }

    private function setupProtectedRegistration(): array
    {
        $unit = Unit::create([
            'name' => 'Protected Unit', 'code' => 'PROT-DEL',
            'is_active' => true, 'allow_admin_unit_registration_deletion' => true,
        ]);

        return [$this->registration($unit), $this->staff($unit)];
    }

    private function assertProtectedDeletion(Registration $registration, User $staff, string $expectedReason): void
    {
        $service = app(ControlledDeletionService::class);
        $this->assertStringContainsString($expectedReason, $service->registrationImpact($registration));

        try {
            $service->deleteRegistration($registration, $staff, 'Permintaan uji', 'HAPUS');
            $this->fail('The protected registration must not be deleted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($expectedReason, $exception->errors()['delete'][0]);
        }

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    private function staff(Unit $unit): User
    {
        Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        $staff = User::factory()->create(['unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true]);
        $staff->assignRole('admin_unit');

        return $staff;
    }

    private function registration(Unit $unit): Registration
    {
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1', 'registration_fee' => 100000, 'status' => 'draft',
        ]);

        return Registration::create([
            'user_id' => User::factory()->create()->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'nik' => '3273010101010001', 'full_name' => 'Calon Siswa',
            'gender' => 'L', 'birth_place' => 'Bandung',
            'birth_date' => '2018-01-01', 'home_address' => 'Bandung',
            'status' => 'submitted', 'current_stage' => 'data_validation',
        ]);
    }

}
