<?php

namespace Tests\Feature;

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

    public function test_registration_with_payment_cannot_be_deleted_even_with_permission(): void
    {
        $unit = Unit::create(['name' => 'Paid Unit', 'code' => 'PAID-REG', 'is_active' => true, 'allow_admin_unit_registration_deletion' => true]);
        $staff = $this->staff($unit);
        $registration = $this->registration($unit);
        Payment::create(['registration_id' => $registration->id, 'status' => 'pending', 'amount' => 100000]);

        $this->expectException(ValidationException::class);
        app(ControlledDeletionService::class)->deleteRegistration($registration, $staff, 'Tidak dipakai', 'HAPUS');
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
