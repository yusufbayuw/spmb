<?php

namespace Tests\Feature;

use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\ControlledDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlledPoolDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unused_va_can_be_deleted_but_assigned_va_is_protected(): void
    {
        [$unit, $staff] = $this->setupUnit();
        $va = VirtualAccount::create(['unit_id' => $unit->id, 'bank' => 'BANK', 'va_number' => 'VA-FREE', 'status' => 'available']);
        app(ControlledDeletionService::class)->deleteVirtualAccount($va, $staff, 'Salah impor', 'HAPUS');
        $this->assertDatabaseMissing('virtual_accounts', ['id' => $va->id]);

        $used = VirtualAccount::create(['unit_id' => $unit->id, 'bank' => 'BANK', 'va_number' => 'VA-USED', 'status' => 'assigned', 'assigned_at' => now()]);
        try {
            app(ControlledDeletionService::class)->deleteVirtualAccount($used, $staff, 'Uji', 'HAPUS');
            $this->fail('Assigned VA must be preserved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delete', $e->errors());
        }
        $this->assertDatabaseHas('virtual_accounts', ['id' => $used->id]);
    }

    public function test_empty_wave_can_be_deleted(): void
    {
        [$unit, $staff] = $this->setupUnit();
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027',
            'wave' => 'Test', 'registration_fee' => 0, 'status' => 'draft',
        ]);
        app(ControlledDeletionService::class)->deleteOpening($opening, $staff, 'Duplikat', 'HAPUS');
        $this->assertDatabaseMissing('registration_openings', ['id' => $opening->id]);
    }

    private function setupUnit(): array
    {
        $unit = Unit::create([
            'name' => 'Delete Pool', 'code' => 'POOLDEL', 'is_active' => true,
            'allow_admin_unit_va_deletion' => true, 'allow_admin_unit_opening_deletion' => true,
        ]);
        Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        $staff = User::factory()->create(['unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true]);
        $staff->assignRole('admin_unit');

        return [$unit, $staff];
    }
}
