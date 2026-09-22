<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\PublicInformationSettings;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AdminUnitRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicInformationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unit_can_edit_only_its_public_profile(): void
    {
        $this->seed(AdminUnitRoleSeeder::class);

        $unit = Unit::create([
            'name' => 'SMA Konten',
            'code' => 'SMAK',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $otherUnit = Unit::create([
            'name' => 'SMP Lain',
            'code' => 'SMPL',
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
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(PublicInformationSettings::class)
            ->assertSet('unitUuid', $unit->uuid)
            ->fillForm([
                'public_headline' => 'Penerimaan SMA Konten',
                'description' => 'Ringkasan penerimaan yang dikelola admin unit.',
                'public_body' => '<p>Informasi lengkap penerimaan.</p>',
                'public_contact_name' => 'Panitia SMA Konten',
                'public_whatsapp' => '6281234567890',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'public_headline' => 'Penerimaan SMA Konten',
            'public_contact_name' => 'Panitia SMA Konten',
        ]);

        Livewire::test(PublicInformationSettings::class)
            ->set('unitUuid', $otherUnit->uuid)
            ->call('loadUnit')
            ->assertNotFound();
    }
}
