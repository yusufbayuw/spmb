<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\UnitResource;
use App\Filament\Admin\Resources\UnitResource\Pages\EditUnit;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitSelfManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unit_can_update_only_own_public_profile(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'SMA Awal', 'code' => 'SMA', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'admin_unit', 'unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue($staff->can('viewAny', Unit::class));
        $this->assertTrue($staff->can('update', $unit));
        $this->assertFalse($staff->can('create', Unit::class));

        Livewire::test(EditUnit::class, ['record' => $unit->getRouteKey()])
            ->fillForm(['name' => 'SMA Baru', 'description' => 'Informasi sekolah',
                'public_email' => 'helpdesk@example.test'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('SMA Baru', $unit->fresh()->name);
        $this->assertSame('helpdesk@example.test', $unit->fresh()->public_email);
        $this->assertSame('SMA', $unit->fresh()->code);
        $this->assertTrue($unit->fresh()->is_active);
    }

    public function test_admin_unit_cannot_update_other_unit_or_privileged_fields(): void
    {
        $this->seed(ShieldSeeder::class);
        $own = Unit::create(['name' => 'SD Sendiri', 'code' => 'SD', 'is_active' => true]);
        $other = Unit::create(['name' => 'SMP Lain', 'code' => 'SMP', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'admin_unit', 'unit_id' => $own->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse($staff->can('update', $other));
        $this->assertFalse(UnitResource::getEloquentQuery()->whereKey($other->id)->exists());

        Livewire::test(EditUnit::class, ['record' => $own->getRouteKey()])
            ->fillForm([
                'description' => 'Keterangan aman',
                'code' => 'HACK',
                'institution_type' => 'university',
                'is_active' => false,
                'allow_admin_unit_registration_deletion' => true,
            ])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Keterangan aman', $own->fresh()->description);
        $this->assertSame('SD', $own->fresh()->code);
        $this->assertSame('school', $own->fresh()->institution_type);
        $this->assertTrue($own->fresh()->is_active);
        $this->assertFalse($own->fresh()->allow_admin_unit_registration_deletion);
    }
}
