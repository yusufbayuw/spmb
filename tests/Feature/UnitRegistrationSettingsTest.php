<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\UnitRegistrationSettings;
use App\Filament\Admin\Pages\UnitRegistrationFormPreview;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UnitRegistrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_discards_unsaved_changes_on_reload_and_only_persists_manual_save(): void
    {
        [$unit, $staff] = $this->fixture();
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(UnitRegistrationSettings::class);
        $uuid = $page->get('configurationUuid');
        $page->set('data.fields', [[
            'key' => 'custom_new_question',
            'label' => 'Pertanyaan',
            'type' => 'text',
            'active' => true,
            'required' => false,
            'group_key' => 'group_additional',
            'options' => [],
        ]])->assertHasNoErrors();

        $this->assertSame([], UnitConfiguration::where('uuid', $uuid)->firstOrFail()->fields);
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('spmb:unit-editor:'.$staff->id.':'.$uuid));

        Livewire::test(UnitRegistrationSettings::class)
            ->assertSet('data.fields', []);

        Livewire::test(UnitRegistrationSettings::class)
            ->fillForm(['builtin_field_policy' => 'all_required'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('all_required', UnitConfiguration::where('uuid', $uuid)->value('builtin_field_policy'));
        $this->assertSame('draft', UnitConfiguration::where('uuid', $uuid)->value('status'));
    }

    public function test_invalid_configuration_does_not_update_unit_logo(): void
    {
        [$unit, $staff] = $this->fixture();
        Storage::fake('public');
        Storage::disk('public')->putFileAs('units/logos', UploadedFile::fake()->image('original.png'), 'original.png');
        $unit->update(['logo_path' => 'units/logos/original.png']);

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->fillForm([
                'unit_logo_path' => [UploadedFile::fake()->image('replacement.png')],
                'registration_number_digits' => 2,
            ])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame('units/logos/original.png', $unit->fresh()->logo_path);
    }

    public function test_admin_editor_no_longer_has_unsafe_autosave_method(): void
    {
        $this->assertFalse(method_exists(UnitRegistrationSettings::class, 'autosaveDraft'));
        $this->assertStringNotContainsString(
            'wire:poll',
            file_get_contents(resource_path('views/filament/admin/pages/unit-registration-settings.blade.php')),
        );
    }

    public function test_preview_opens_modal_without_publishing_or_saving_incomplete_draft(): void
    {
        [$unit, $staff] = $this->fixture();
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(UnitRegistrationSettings::class)
            ->set('data.fields', [[
                'key' => 'custom_school_question',
                'label' => 'Pertanyaan Sekolah',
                'type' => 'select',
                'active' => true,
                'required' => false,
                'group_key' => 'group_additional',
                'options' => [],
            ]])
            ->call('showPreview')
            ->assertHasNoErrors()
            ->assertSet('preview', true);

        $token = $page->get('previewToken');
        $this->assertNotEmpty($token);
        $this->assertSame($staff->id, \Illuminate\Support\Facades\Cache::get('spmb:form-preview:'.$token)['user_id']);
        $this->assertSame(1, UnitConfiguration::where('unit_id', $unit->id)->where('status', 'published')->count());
        $this->assertSame([], UnitConfiguration::where('unit_id', $unit->id)->where('status', 'draft')->firstOrFail()->fields);
    }

    public function test_preview_fields_are_interactive_but_uploads_are_only_placeholders(): void
    {
        [$unit, $staff] = $this->fixture();
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(UnitRegistrationSettings::class)
            ->call('showPreview')
            ->assertHasNoErrors();

        $fields = $page->instance()->previewForm->getFlatFields();
        $nik = collect($fields)->first(fn ($field): bool => $field->getName() === 'nik');
        $this->assertNotNull($nik);
        $this->assertFalse($nik->isDisabled());
        $this->assertTrue($nik->isMarkedAsRequired());

        $config = UnitConfiguration::query()->where('unit_id', $unit->id)->where('status', 'draft')->firstOrFail();
        $definition = [
            'key' => 'document_proof',
            'label' => 'Bukti Dokumen',
            'type' => 'file',
            'active' => true,
            'required' => true,
            'formats' => ['pdf'],
        ];

        $configured = app(\App\Services\ConfiguredRegistrationForm::class);
        $this->assertInstanceOf(
            \Filament\Forms\Components\Placeholder::class,
            $configured->field($definition, $config, true),
        );
        $this->assertInstanceOf(
            \Filament\Forms\Components\Group::class,
            $configured->field($definition, $config, false),
        );
    }

    public function test_new_tab_preview_registers_its_form_and_never_exposes_submission(): void
    {
        [$unit, $staff] = $this->fixture();
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $editor = Livewire::test(UnitRegistrationSettings::class)
            ->call('showPreview')->assertHasNoErrors();

        $preview = Livewire::withQueryParams(['token' => $editor->get('previewToken')])
            ->test(UnitRegistrationFormPreview::class)
            ->assertSee('Mode pratinjau')
            ->assertDontSee('Kirim Pendaftaran');

        $nik = collect($preview->instance()->previewForm->getFlatFields())
            ->first(fn ($field): bool => $field->getName() === 'nik');
        $this->assertNotNull($nik);
        $this->assertFalse($nik->isDisabled());
        $this->assertTrue($nik->isMarkedAsRequired());
        $this->assertDatabaseCount('registrations', 0);
    }

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


    public function test_admin_unit_can_reorder_and_hide_optional_applicant_portal_blocks(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $blocks = [
            ['key' => 'summary', 'active' => true],
            ['key' => 'payment', 'active' => false],
            ['key' => 'required_documents', 'active' => true],
            ['key' => 'additional_information', 'active' => false],
            ['key' => 'selection_tests', 'active' => true],
            ['key' => 'announcement', 'active' => true],
            ['key' => 'post_announcement', 'active' => true],
        ];

        Livewire::test(UnitRegistrationSettings::class)
            ->assertSee('Tata Letak Portal Pendaftar')
            ->assertSee('Aksi Selanjutnya')
            ->assertSee('Tahapan Pendaftaran')
            ->fillForm(['applicant_portal_blocks' => $blocks])
            ->call('publish')
            ->assertHasNoFormErrors();

        $published = UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'published')
            ->latest('version')
            ->firstOrFail();

        $this->assertSame(
            array_column($blocks, 'key'),
            collect($published->applicant_portal_blocks)->pluck('key')->all(),
        );
        $this->assertFalse(
            (bool) collect($published->applicant_portal_blocks)->firstWhere('key', 'payment')['active'],
        );
        $this->assertFalse(
            (bool) collect($published->applicant_portal_blocks)->firstWhere('key', 'additional_information')['active'],
        );
    }

    public function test_admin_unit_can_configure_applicant_card_header(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->assertSee('Kartu Peserta')
            ->assertSee('Label Header Kartu Pendaftaran')
            ->assertSee('Judul Header Kartu')
            ->assertSee('Tahun Ajaran {{ tahun_ajaran }}')
            ->fillForm([
                'applicant_card_header_label' => 'KARTU PESERTA',
                'applicant_card_header_title' => 'SD Contoh',
            ])
            ->call('publish')
            ->assertHasNoFormErrors();

        $published = UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'published')
            ->latest('version')
            ->firstOrFail();

        $this->assertSame('KARTU PESERTA', $published->applicant_card_header_label);
        $this->assertSame('SD Contoh', $published->applicant_card_header_title);
    }

    public function test_admin_unit_can_configure_participant_card_mode(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->assertSee('Jenis Kartu Peserta')
            ->assertSee('Keduanya')
            ->assertSee('Hanya Kartu Pendaftaran')
            ->assertSee('Hanya Kartu Tes')
            ->fillForm([
                'participant_card_mode' => UnitConfiguration::PARTICIPANT_CARD_MODE_REGISTRATION_ONLY,
            ])
            ->call('publish')
            ->assertHasNoFormErrors();

        $published = UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'published')
            ->latest('version')
            ->firstOrFail();

        $this->assertSame(
            UnitConfiguration::PARTICIPANT_CARD_MODE_REGISTRATION_ONLY,
            $published->participant_card_mode,
        );
    }

    public function test_test_only_card_mode_requires_enabled_required_test(): void
    {
        [, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->fillForm([
                'participant_card_mode' => UnitConfiguration::PARTICIPANT_CARD_MODE_TEST_ONLY,
            ])
            ->call('publish')
            ->assertHasFormErrors(['participant_card_mode'])
            ->assertNotified('Publikasi konfigurasi gagal');
    }

    public function test_admin_unit_can_configure_versioned_pre_form_consent(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->assertSee('Persetujuan Sebelum Formulir')
            ->fillForm([
                'pre_form_consent.enabled' => true,
                'pre_form_consent.title' => 'Persetujuan Khusus Unit',
                'pre_form_consent.content' => '<p>Ketentuan <strong>khusus</strong> untuk {{ unit_name }}.</p>',
                'pre_form_consent.confirmation_text' => 'Saya menyetujui ketentuan {{ unit_name }}.',
            ])
            ->call('publish')
            ->assertHasNoFormErrors();

        $published = UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'published')
            ->latest('version')
            ->firstOrFail();

        $this->assertTrue($published->pre_form_consent['enabled']);
        $this->assertSame('Persetujuan Khusus Unit', $published->pre_form_consent['title']);
        $this->assertStringContainsString('<strong>khusus</strong>', $published->pre_form_consent['content']);
        $this->assertSame(
            'Saya menyetujui ketentuan {{ unit_name }}.',
            $published->pre_form_consent['confirmation_text'],
        );
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
