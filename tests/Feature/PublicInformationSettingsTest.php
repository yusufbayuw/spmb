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
                'public_body' => '<p>Informasi <strong>lengkap</strong> penerimaan.</p><ol><li>Tahap satu</li></ol>',
                'pre_registration_heading' => 'Sebelum mengisi formulir',
                'pre_registration_body' => '<p>Siapkan dokumen dengan <strong>teliti</strong>.</p>',
                'pre_registration_items' => [
                    ['title' => 'Kartu Keluarga', 'description' => 'Pastikan data terbaca.'],
                    ['title' => 'Rapor', 'description' => 'Gunakan rapor terbaru.'],
                ],
                'public_contact_name' => 'Panitia SMA Konten',
                'public_whatsapp' => '6281234567890',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'public_headline' => 'Penerimaan SMA Konten',
            'public_contact_name' => 'Panitia SMA Konten',
            'pre_registration_heading' => 'Sebelum mengisi formulir',
        ]);
        $this->assertSame(
            'Kartu Keluarga',
            Unit::query()->findOrFail($unit->id)->pre_registration_items[0]['title'],
        );

        $component = Livewire::test(PublicInformationSettings::class);

        $this->assertSame(
            [$unit->uuid => $unit->name],
            $component->instance()->units(),
        );
        $this->assertArrayNotHasKey($otherUnit->uuid, $component->instance()->units());
    }
}
