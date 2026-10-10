<?php

namespace Tests\Feature;

use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\ControlledDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
