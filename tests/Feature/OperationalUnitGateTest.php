<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\PublicInformationSettings;
use App\Filament\Admin\Pages\TestSessions;
use App\Filament\Admin\Pages\UnitConfigurationTransfer;
use App\Filament\Admin\Pages\UnitRegistrationSettings;
use App\Filament\Admin\Resources\RegistrationResource as AdminRegistrationResource;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalReportService;
use Database\Seeders\AdminUnitRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalUnitGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_off_hides_operational_data_without_mutating_children_and_on_restores_it(): void
    {
        $unit = Unit::create([
            'name' => 'SMP Operasional',
            'code' => 'SMPOP',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        $pathway = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 250000,
            'status' => 'open',
            'opened_at' => now()->subDay(),
            'closed_at' => now()->addMonth(),
        ]);

        $applicant = User::factory()->create(['is_active' => true]);
        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway->id,
            'nik' => '3273010101010001',
            'full_name' => 'Calon Operasional',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2013-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'lifecycle_status' => 'active',
            'data_validation_status' => 'pending',
        ]);

        $this->assertTrue($registration->fresh()->isOperational());
        $this->assertTrue(RegistrationOpening::query()->visibleToApplicants()->whereKey($opening->id)->exists());
        $this->assertTrue(AdminRegistrationResource::getEloquentQuery()->whereKey($registration->id)->exists());

        $unit->update(['is_active' => false]);

        $this->assertFalse(Unit::query()->operational()->whereKey($unit->id)->exists());
        $this->assertFalse($registration->fresh()->isOperational());
        $this->assertFalse(RegistrationOpening::query()->visibleToApplicants()->whereKey($opening->id)->exists());
        $this->assertFalse(AdminRegistrationResource::getEloquentQuery()->whereKey($registration->id)->exists());

        $this->assertSame('open', $opening->fresh()->status);
        $this->assertTrue($pathway->fresh()->is_active);
        $this->assertSame('active', $registration->fresh()->lifecycle_status);

        $unit->update(['is_active' => true]);

        $this->assertTrue($registration->fresh()->isOperational());
        $this->assertTrue(RegistrationOpening::query()->visibleToApplicants()->whereKey($opening->id)->exists());
        $this->assertTrue(AdminRegistrationResource::getEloquentQuery()->whereKey($registration->id)->exists());
    }

    public function test_admin_unit_configuration_surfaces_are_disabled_while_its_unit_is_off(): void
    {
        $this->seed(AdminUnitRoleSeeder::class);

        $unit = Unit::create([
            'name' => 'SMP Admin Unit',
            'code' => 'SMPAU',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $admin->assignRole('admin_unit');

        $this->actingAs($admin);

        $this->assertTrue($admin->hasOperationalUnitAccess());
        $this->assertTrue(UnitRegistrationSettings::canAccess());
        $this->assertTrue(PublicInformationSettings::canAccess());
        $this->assertTrue(UnitConfigurationTransfer::canAccess());
        $this->assertTrue(TestSessions::canAccess());

        $unit->update(['is_active' => false]);

        $this->assertFalse($admin->fresh()->hasOperationalUnitAccess());
        $this->assertFalse(UnitRegistrationSettings::canAccess());
        $this->assertFalse(PublicInformationSettings::canAccess());
        $this->assertFalse(UnitConfigurationTransfer::canAccess());
        $this->assertFalse(TestSessions::canAccess());

        $unit->update(['is_active' => true]);

        $this->assertTrue($admin->fresh()->hasOperationalUnitAccess());
        $this->assertTrue(UnitRegistrationSettings::canAccess());
    }

    public function test_operational_report_defaults_to_active_lifecycle_and_never_includes_inactive_units(): void
    {
        $activeUnit = Unit::create([
            'name' => 'SMA Aktif',
            'code' => 'SMAA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $inactiveUnit = Unit::create([
            'name' => 'SMA Nonaktif',
            'code' => 'SMAN',
            'institution_type' => 'school',
            'is_active' => false,
        ]);

        $activeOpening = RegistrationOpening::create([
            'unit_id' => $activeUnit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 100000,
            'status' => 'open',
        ]);
        $inactiveOpening = RegistrationOpening::create([
            'unit_id' => $inactiveUnit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 900000,
            'status' => 'open',
        ]);

        $applicant = User::factory()->create(['is_active' => true]);
        $staff = User::factory()->create(['is_active' => true]);

        Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $activeUnit->id,
            'registration_opening_id' => $activeOpening->id,
            'nik' => '3273010101010002',
            'full_name' => 'Aktif',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2013-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'lifecycle_status' => 'active',
            'data_validation_status' => 'pending',
        ]);

        Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $activeUnit->id,
            'registration_opening_id' => $activeOpening->id,
            'nik' => '3273010101010003',
            'full_name' => 'Arsip',
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2013-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'completed',
            'lifecycle_status' => 'archived',
            'data_validation_status' => 'approved',
        ]);

        Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $inactiveUnit->id,
            'registration_opening_id' => $inactiveOpening->id,
            'nik' => '3273010101010004',
            'full_name' => 'Unit Mati',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2013-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'lifecycle_status' => 'active',
            'data_validation_status' => 'pending',
        ]);

        $reports = app(OperationalReportService::class);

        $this->assertSame(1, $reports->summary($staff)['total']);
        $this->assertSame(1, $reports->summary($staff, ['lifecycle_status' => 'archived'])['total']);
    }
}
