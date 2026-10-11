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

class RegistrationTutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_hides_tutorial_section_if_all_units_have_no_video(): void
    {
        Unit::create(['name' => 'SMP Tanpa Video', 'code' => 'SMPA', 'institution_type' => 'school', 'is_active' => true]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Video Tutorial Pendaftaran')
            ->assertSee('Empat langkah untuk memulai');
    }

    public function test_homepage_displays_only_active_units_with_valid_youtube_urls(): void
    {
        Unit::create(['name' => 'SMP Video', 'code' => 'SMPV', 'institution_type' => 'school', 'is_active' => true,
            'registration_tutorial_url' => 'https://youtu.be/dQw4w9WgXcQ']);
        Unit::create(['name' => 'SMA Tanpa Video', 'code' => 'SMAT', 'institution_type' => 'school', 'is_active' => true]);
        Unit::create(['name' => 'SMA Nonaktif', 'code' => 'SMAN', 'institution_type' => 'school', 'is_active' => false,
            'registration_tutorial_url' => 'https://youtu.be/aqz-KE-bpKQ']);
        Unit::create(['name' => 'SMP URL Tidak Valid', 'code' => 'SMPI', 'institution_type' => 'school', 'is_active' => true,
            'registration_tutorial_url' => 'https://example.com/watch?v=aqz-KE-bpKQ']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Video Tutorial Pendaftaran')
            ->assertSee('SMP Video')
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('SMA Tanpa Video')
            ->assertDontSee('SMA Nonaktif')
            ->assertDontSee('SMP URL Tidak Valid')
            ->assertDontSee('>Pilih jenjang</p>', false);
    }

    public function test_admin_unit_can_save_youtube_tutorial_for_own_unit(): void
    {
        $this->seed(AdminUnitRoleSeeder::class);

        $own = Unit::create(['name' => 'Unit Sendiri', 'code' => 'SMPA', 'institution_type' => 'school', 'is_active' => true]);
        $other = Unit::create(['name' => 'Unit Lain', 'code' => 'SMAB', 'institution_type' => 'school', 'is_active' => true]);

        $admin = User::factory()->create(['role' => 'admin_unit', 'unit_id' => $own->id, 'is_active' => true]);
        $admin->assignRole('admin_unit');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(PublicInformationSettings::class)
            ->assertSet('unitUuid', $own->uuid)
            ->fillForm(['registration_tutorial_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $own->fresh()->registration_tutorial_url);
        $this->assertNull($other->fresh()->registration_tutorial_url);
    }

    public function test_admin_unit_cannot_assign_video_to_other_unit(): void
    {
        $this->seed(AdminUnitRoleSeeder::class);

        $own = Unit::create(['name' => 'Unit Sendiri', 'code' => 'SMPA', 'institution_type' => 'school', 'is_active' => true]);
        $other = Unit::create(['name' => 'Unit Lain', 'code' => 'SMAB', 'institution_type' => 'school', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin_unit', 'unit_id' => $own->id, 'is_active' => true]);
        $admin->assignRole('admin_unit');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(PublicInformationSettings::class)
            ->set('unitUuid', $other->uuid)
            ->call('save')
            ->assertNotFound();

        $this->assertNull($other->fresh()->registration_tutorial_url);
    }

    public function test_admin_unit_rejects_untrusted_video_url(): void
    {
        $this->seed(AdminUnitRoleSeeder::class);
        $own = Unit::create(['name' => 'Unit Sendiri', 'code' => 'SMPA', 'institution_type' => 'school', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin_unit', 'unit_id' => $own->id, 'is_active' => true]);
        $admin->assignRole('admin_unit');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(PublicInformationSettings::class)
            ->fillForm(['registration_tutorial_url' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ'])
            ->call('save')
            ->assertHasFormErrors(['registration_tutorial_url']);

        $this->assertNull($own->fresh()->registration_tutorial_url);
    }
}
