<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\AppBrandingSettings;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\AppBrandingService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppBrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_environment_values_remain_fallback_until_database_settings_exist(): void
    {
        config()->set('spmb.portal.name', 'Portal Dari Env');
        config()->set('spmb.portal.foundation_name', 'Institusi Dari Env');

        $service = app(AppBrandingService::class);
        $service->refresh();

        $this->assertNull($service->settings());
        $this->assertSame('Portal Dari Env', $service->portalName());
        $this->assertSame('Institusi Dari Env', $service->organizationName());
    }

    public function test_super_admin_can_save_global_white_label_settings_and_manifest_uses_them(): void
    {
        $this->seed(ShieldSeeder::class);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AppBrandingSettings::class)
            ->fillForm([
                'portal_name' => 'Portal Penerimaan Contoh',
                'organization_name' => 'Institusi Contoh',
                'website_url' => 'https://example.test',
                'email' => 'info@example.test',
                'phone' => '022123456',
                'whatsapp' => '628123456789',
                'address' => 'Jalan Contoh 1',
                'service_hours' => 'Senin–Jumat 08.00–16.00',
                'logo_path' => null,
                'theme_color' => '#123456',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('app_settings', [
            'portal_name' => 'Portal Penerimaan Contoh',
            'organization_name' => 'Institusi Contoh',
            'theme_color' => '#123456',
        ]);

        $service = app(AppBrandingService::class);
        $service->refresh();

        $this->assertSame('Portal Penerimaan Contoh', $service->portalName());
        $this->assertSame('Institusi Contoh', $service->organizationName());

        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('name', 'Portal Penerimaan Contoh')
            ->assertJsonPath('theme_color', '#123456');
    }

    public function test_admin_unit_cannot_access_global_white_label_settings(): void
    {
        $this->seed(ShieldSeeder::class);

        $adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'is_active' => true,
        ]);
        $adminUnit->assignRole('admin_unit');

        $this->actingAs($adminUnit);

        $this->assertFalse(AppBrandingSettings::canAccess());
    }
}
