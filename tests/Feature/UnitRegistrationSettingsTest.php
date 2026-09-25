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

    public function test_incomplete_live_repeater_labels_do_not_crash_select_rendering(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->set('data.fields', [[
                'key' => 'custom_note',
                'label' => 'Catatan',
                'type' => 'text',
                'active' => true,
                'required' => false,
                'group' => null,
                'group_key' => 'group_additional',
                'help' => null,
                'options' => [],
            ]])
            ->set('data.form_groups', [
                ['key' => 'group_additional', 'label' => 'Informasi Tambahan'],
                ['key' => 'group_new', 'label' => null],
            ])
            ->set('data.academic_scores_enabled', true)
            ->set('data.academic_score_settings.grades', [
                ['key' => 'grade_7', 'label' => 'Kelas VII'],
                ['key' => 'grade_new', 'label' => null],
            ])
            ->assertSet('data.form_groups.1.key', 'group_new')
            ->assertSet('data.form_groups.1.label', null)
            ->assertSet('data.academic_score_settings.grades.1.key', 'grade_new')
            ->assertSet('data.academic_score_settings.grades.1.label', null);
    }

    public function test_admin_unit_can_configure_registration_number_prefix_and_digits(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->assertSee('Nomor Pendaftaran')
            ->assertSee('Prefix')
            ->assertSee('Jumlah Digit Angka')
            ->fillForm([
                'registration_number_prefix' => 'smp',
                'registration_number_digits' => 3,
            ])
            ->call('publish')
            ->assertHasNoFormErrors();

        $published = UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'published')
            ->latest('version')
            ->firstOrFail();

        $this->assertSame('SMP', $published->registration_number_prefix);
        $this->assertSame(3, $published->registration_number_digits);
    }

    public function test_admin_unit_can_configure_boolean_detail_per_answer(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->fillForm([
                'fields' => [[
                    'key' => 'custom_condition',
                    'label' => 'Apakah ada kondisi khusus?',
                    'type' => 'boolean',
                    'active' => true,
                    'required' => true,
                    'group' => 'Informasi Tambahan',
                    'group_key' => 'group_additional',
                    'help' => null,
                    'options' => [],
                    'boolean_yes_detail_enabled' => true,
                    'boolean_yes_detail_label' => 'Tuliskan kondisi',
                    'boolean_yes_detail_required' => false,
                    'boolean_no_detail_enabled' => false,
                    'boolean_no_detail_label' => 'Keterangan',
                    'boolean_no_detail_required' => false,
                ]],
            ])
            ->assertSee('Keterangan saat jawaban Ya')
            ->assertSee('Keterangan saat jawaban Tidak')
            ->call('publish')
            ->assertHasNoFormErrors();

        $published = UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'published')
            ->latest('version')
            ->firstOrFail();

        $field = collect($published->fields)->firstWhere('key', 'custom_condition');

        $this->assertTrue($field['boolean_yes_detail_enabled']);
        $this->assertSame('Tuliskan kondisi', $field['boolean_yes_detail_label']);
        $this->assertFalse($field['boolean_yes_detail_required']);
        $this->assertFalse($field['boolean_no_detail_enabled']);
        $this->assertFalse($field['boolean_no_detail_required']);
    }

    public function test_tu_cannot_access_unit_registration_settings(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SD Test',
            'code' => 'SD',
            'is_active' => true,
        ]);
        $tu = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $tu->assignRole('tu');

        $this->actingAs($tu);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(UnitRegistrationSettings::canAccess());
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
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('admin_unit');

        return [$unit, $staff];
    }
}
