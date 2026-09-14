<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\UnitRegistrationSettings;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitRegistrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_confirms_the_activated_version_after_loading_the_next_draft(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->call('publish')
            ->assertHasNoFormErrors()
            ->assertNotified('Publikasi v2 berhasil');

        $this->assertSame(2, UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'published')
            ->orderByDesc('version')
            ->value('version'));
        $this->assertSame(3, UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'draft')
            ->value('version'));
    }

    public function test_publish_validation_failure_is_visible_to_the_user(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->fillForm([
                'fields' => [[
                    'key' => 'province_code',
                    'label' => 'Provinsi',
                    'type' => 'select',
                    'active' => true,
                    'required' => false,
                    'group' => 'Rincian Alamat',
                    'help' => null,
                    'options' => [],
                ]],
            ])
            ->call('publish')
            ->assertHasFormErrors(['fields'])
            ->assertNotified('Publikasi konfigurasi gagal');

        $this->assertSame(1, UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'published')
            ->orderByDesc('version')
            ->value('version'));
        $this->assertSame(2, UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'draft')
            ->value('version'));
    }

    /** @return array{Unit, User} */
    private function fixture(): array
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SD Test',
            'code' => 'SD',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('tu');

        return [$unit, $staff];
    }
}
