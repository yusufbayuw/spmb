<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\HomepageSettings;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\HomepageSectionService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomepageSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_homepage_blocks_remain_visible_by_default(): void
    {
        $this->assertSame(HomepageSectionService::defaults(), app(HomepageSectionService::class)->visibility());

        $this->get('/')
            ->assertOk()
            ->assertSee('id="pendaftaran"', false)
            ->assertSee('id="cara-mendaftar"', false)
            ->assertSee('id="bantuan"', false)
            ->assertSee('Siapkan informasi utama');
    }

    public function test_super_admin_can_hide_two_sections_and_navigation_links(): void
    {
        $this->seed(ShieldSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $blocks = HomepageSectionService::defaults();
        $blocks['steps'] = false;
        $blocks['helpdesk'] = false;

        Livewire::test(HomepageSettings::class)
            ->fillForm(['blocks' => $blocks])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($blocks, AppSetting::query()->firstOrFail()->homepage_blocks);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="pendaftaran"', false)
            ->assertDontSee('id="cara-mendaftar"', false)
            ->assertDontSee('id="bantuan"', false)
            ->assertDontSee('#cara-mendaftar', false)
            ->assertDontSee('#bantuan', false);

        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admissions.apply'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('registration.show'));
    }

    public function test_disabling_registration_only_hides_homepage_entry(): void
    {
        AppSetting::query()->create([
            'portal_name' => 'SPMB',
            'organization_name' => 'Institusi Uji',
            'homepage_blocks' => ['registration' => false],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('id="pendaftaran"', false)
            ->assertDontSee('#pendaftaran', false)
            ->assertSee('id="cara-mendaftar"', false);

        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admissions.apply'));
    }

    public function test_missing_and_invalid_keys_preserve_other_defaults(): void
    {
        $blocks = HomepageSectionService::normalize([
            'tutorials' => false,
            'unknown' => false,
            'registration' => 'false',
        ]);

        $this->assertFalse($blocks['tutorials']);
        $this->assertTrue($blocks['registration']);
        $this->assertTrue($blocks['helpdesk']);
        $this->assertCount(8, $blocks);
    }

    public function test_only_active_super_admin_has_cms_access(): void
    {
        $this->seed(ShieldSeeder::class);

        foreach (['admin_unit', 'tu', 'pendaftar'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $user->assignRole($role);
            $this->actingAs($user);
            $this->assertFalse(HomepageSettings::canAccess());
        }

        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => false]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);
        $this->assertFalse(HomepageSettings::canAccess());
    }
}
