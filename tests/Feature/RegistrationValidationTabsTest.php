<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationValidationTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_tabs_and_counter_are_scoped_to_actionable_records_in_staff_unit(): void
    {
        $this->seed(ShieldSeeder::class);

        $ownUnit = Unit::create([
            'name' => 'Unit Saya',
            'code' => 'VAL-OWN',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $otherUnit = Unit::create([
            'name' => 'Unit Lain',
            'code' => 'VAL-OTHER',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $ownUnit->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('tu');

        $pending = $this->registration($ownUnit, 1, 'pending');
        $revision = $this->registration($ownUnit, 2, 'revision');
        $valid = $this->registration($ownUnit, 3, 'valid', 'payment');
        $cancelled = $this->registration($ownUnit, 4, 'pending', 'data_validation', 'cancelled');
        $archived = $this->registration($ownUnit, 5, 'revision', 'data_validation', 'archived');
        $pastStage = $this->registration($ownUnit, 6, 'pending', 'payment');
        $foreignPending = $this->registration($otherUnit, 7, 'pending');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $list = Livewire::test(ListRegistrations::class)->assertSuccessful();
        $tabs = $list->instance()->getTabs();

        $this->assertSame(['all', 'validated', 'pending_validation'], array_keys($tabs));
        $this->assertSame(2, (int) $tabs['pending_validation']->getBadge());

        $list->set('activeTab', 'pending_validation')
            ->assertCanSeeTableRecords([$pending, $revision])
            ->assertCanNotSeeTableRecords([$valid, $cancelled, $archived, $pastStage, $foreignPending]);

        $list->set('activeTab', 'validated')
            ->assertCanSeeTableRecords([$valid])
            ->assertCanNotSeeTableRecords([$pending, $revision, $foreignPending]);

        // "Semua" really is all lifecycles; withdrawn/cancelled/archived
        // remain accessible through the existing table filter.
        $list->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$pending, $revision, $valid, $cancelled, $archived, $pastStage])
            ->assertCanNotSeeTableRecords([$foreignPending]);

        $list->filterTable('lifecycle_status', 'cancelled')
            ->assertCanSeeTableRecords([$cancelled])
            ->assertCanNotSeeTableRecords([$pending, $revision, $valid, $archived]);
    }

    public function test_validation_counter_updates_without_counting_other_units(): void
    {
        $this->seed(ShieldSeeder::class);

        $ownUnit = Unit::create([
            'name' => 'Unit Operator',
            'code' => 'VAL-OP',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $otherUnit = Unit::create([
            'name' => 'Unit Tetangga',
            'code' => 'VAL-NEIGHBOR',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $ownUnit->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $adminUnit->assignRole('admin_unit');

        $record = $this->registration($ownUnit, 11, 'pending');
        $this->registration($otherUnit, 12, 'pending');

        $this->actingAs($adminUnit);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame(
            1,
            (int) Livewire::test(ListRegistrations::class)->instance()->getTabs()['pending_validation']->getBadge(),
        );

        $record->update(['data_validation_status' => 'valid', 'current_stage' => 'virtual_account']);

        $this->assertSame(
            0,
            (int) Livewire::test(ListRegistrations::class)->instance()->getTabs()['pending_validation']->getBadge(),
        );
    }

    private function registration(
        Unit $unit,
        int $index,
        string $validationStatus,
        string $stage = 'data_validation',
        string $lifecycle = 'active',
    ): Registration {
        $opening = RegistrationOpening::firstOrCreate(
            ['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1'],
            ['status' => 'open', 'registration_fee' => 0],
        );

        return Registration::create([
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_number' => 'VAL-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'full_name' => 'Peserta Validasi '.$index,
            'nik' => '327301010101'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2013-01-01',
            'home_address' => 'Alamat Tes',
            'status' => 'submitted',
            'current_stage' => $stage,
            'data_validation_status' => $validationStatus,
            'lifecycle_status' => $lifecycle,
        ]);
    }
}
