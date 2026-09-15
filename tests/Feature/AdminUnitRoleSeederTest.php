<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\TestSessions;
use App\Filament\Admin\Pages\UnitRegistrationSettings;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AdminUnitRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUnitRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_splits_admin_unit_from_operational_tu(): void
    {
        $this->seed(AdminUnitRoleSeeder::class);

        $adminUnit = Role::findByName('admin_unit', 'web');
        $tu = Role::findByName('tu', 'web');

        $adminUnitPermissionCount = $adminUnit->permissions()->count();
        $tuPermissionCount = $tu->permissions()->count();

        $this->seed(AdminUnitRoleSeeder::class);

        $this->assertSame(1, Role::query()->where('name', 'admin_unit')->count());
        $this->assertSame(1, Role::query()->where('name', 'tu')->count());
        $this->assertSame($adminUnitPermissionCount, $adminUnit->fresh()->permissions()->count());
        $this->assertSame($tuPermissionCount, $tu->fresh()->permissions()->count());

        foreach ([
            'view_any_registration',
            'verify_payment_payment',
            'verify_document_document',
            'record_result_admissiontestresult',
            'decide_selection',
            'finalize_selectionbatch',
            'publish_announcement',
            'enroll_registration',
        ] as $permission) {
            $this->assertTrue($adminUnit->fresh()->hasPermissionTo($permission));
            $this->assertTrue($tu->fresh()->hasPermissionTo($permission));
        }

        foreach ([
            'view_any_registrationopening',
            'create_registrationpathway',
            'view_any_studyprogram',
            'view_any_virtualaccount',
            'view_any_admissionquota',
            'view_any_admissiontest',
            'view_any_unit',
            'view_any_parentinfo',
            'view_any_auditlog',
        ] as $permission) {
            $this->assertTrue($adminUnit->fresh()->hasPermissionTo($permission));
            $this->assertFalse($tu->fresh()->hasPermissionTo($permission));
        }
    }

    public function test_admin_unit_and_tu_share_unit_scope_but_only_admin_unit_can_open_configuration_pages(): void
    {
        $this->seed(AdminUnitRoleSeeder::class);

        $unit = Unit::create([
            'name' => 'Unit Role Test',
            'code' => 'ROLE-TEST',
            'is_active' => true,
        ]);

        $adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $adminUnit->assignRole('admin_unit');

        $tu = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $tu->assignRole('tu');

        $this->assertTrue($adminUnit->isAdminUnit());
        $this->assertTrue($adminUnit->isTU());
        $this->assertFalse($tu->isAdminUnit());
        $this->assertTrue($tu->isTU());

        $this->actingAs($tu);
        $this->assertFalse(UnitRegistrationSettings::canAccess());
        $this->assertFalse(TestSessions::canAccess());

        $this->actingAs($adminUnit);
        $this->assertTrue(UnitRegistrationSettings::canAccess());
        $this->assertTrue(TestSessions::canAccess());
    }
}
