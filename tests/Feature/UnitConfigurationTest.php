<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\UnitRegistrationSettings;
use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Selection;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\ConfiguredRegistrationForm;
use App\Services\RegistrationWorkflowService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UnitConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_changes_apply_only_to_new_registrations(): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $old = $service->initialize($unit);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['documents_enabled'] = false;
        $published = $service->save($draft, $staff, $data, true);

        $this->assertSame($old->id, $registration->fresh()->unit_configuration_id);
        $this->assertTrue($registration->fresh()->configuration->documents_enabled);
        $this->assertSame($published->id, $service->current($unit->id)->id);
        $this->expectException(ValidationException::class);
        $published->update(['documents_enabled' => true]);
    }

    public function test_admin_unit_cannot_edit_configuration_of_other_unit(): void
    {
        [$unit, $staff] = $this->fixture();
        $other = Unit::create(['name' => 'Other', 'code' => 'OTHER', 'is_active' => true]);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(UnitRegistrationSettings::class)->set('unitUuid', $other->uuid)->call('loadUnit')->assertForbidden();
        $this->assertDatabaseMissing('unit_configurations', ['unit_id' => $other->id]);
        $this->assertFalse($staff->can('configureRegistration', $other));
        $this->assertTrue($staff->can('configureRegistration', $unit));
    }

    public function test_initial_version_migration_preserves_legacy_stage_and_is_repeatable(): void
    {
        [$unit, , $registration] = $this->fixture();
        $migration = require database_path('migrations/2026_09_05_125335_initialize_unit_configuration_versions.php');
        $migration->up();
        $configurationId = $registration->fresh()->unit_configuration_id;
        $migration->up();

        $this->assertNotNull($configurationId);
        $this->assertSame($configurationId, $registration->fresh()->unit_configuration_id);
        $this->assertSame('data_validation', $registration->fresh()->current_stage);
        $this->assertSame(1, UnitConfiguration::where('unit_id', $unit->id)->count());
        $this->assertTrue($registration->fresh()->configuration->legacy);
    }

    public function test_admin_unit_settings_and_full_form_preview_render(): void
    {
        [, $staff] = $this->fixture();
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(UnitRegistrationSettings::class)
            ->assertSee('Pengaturan Pendaftaran Unit')
            ->assertSee('Metode Penetapan Hasil')
            ->assertSee('Proses Pasca-Pengumuman')
            ->assertSee('Nama Tahapan di Portal Pendaftar')
            ->assertSee('Kebijakan Isian Bawaan')
            ->assertSee('Wajibkan semua isian bawaan')
            ->call('showPreview')
            ->assertHasNoFormErrors()
            ->assertSee('Nama Lengkap');
    }

    public function test_admin_unit_can_save_unit_logo_without_putting_it_in_versioned_configuration(): void
    {
        [$unit, $staff] = $this->fixture();

        Storage::fake('public');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->fillForm([
                'unit_logo_path' => [
                    UploadedFile::fake()->image('sd-test.png', 120, 120),
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $logoPath = $unit->fresh()->logo_path;
        $this->assertNotNull($logoPath);
        $this->assertStringStartsWith('units/logos/', $logoPath);
        Storage::disk('public')->assertExists($logoPath);

        $draft = UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'draft')
            ->firstOrFail()
            ->toArray();

        $this->assertArrayNotHasKey('unit_logo_path', $draft);
    }

    public function test_admin_unit_can_customize_template_stage_labels(): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['workflow_stage_labels']['selection'] = 'Seleksi Calon Siswa';

        $published = $service->save($draft, $staff, $data, true);

        $registration->update([
            'unit_configuration_id' => $published->id,
            'current_stage' => 'selection',
        ]);

        $registration->refresh();

        $this->assertSame('Seleksi Calon Siswa', $registration->stageLabel());
        $this->assertSame('Seleksi Calon Siswa', $registration->progressStages()['selection']);
    }

    public function test_all_required_builtin_policy_requires_defaults_without_expanding_every_field(): void
    {
        [$unit, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['builtin_field_policy'] = 'all_required';
        $data['fields'] = [[
            'key' => 'nickname',
            'label' => 'Nama Panggilan',
            'type' => 'text',
            'active' => true,
            'required' => false,
            'group' => null,
            'help' => 'Pengecualian: nama panggilan tidak wajib.',
            'options' => [],
        ]];

        $saved = $service->save($draft, $staff, $data);
        $configuredForm = app(ConfiguredRegistrationForm::class);

        $this->assertSame('all_required', $saved->builtin_field_policy);
        $this->assertCount(1, $saved->fields);
        $this->assertSame(
            ['active' => true, 'required' => true, 'overridden' => false],
            $configuredForm->builtinFieldState($saved, 'phone'),
        );
        $this->assertSame(
            ['active' => true, 'required' => false, 'overridden' => true],
            $configuredForm->builtinFieldState($saved, 'nickname'),
        );
        $this->assertSame(
            ['active' => true, 'required' => true, 'overridden' => false],
            $configuredForm->builtinFieldState($saved, 'province_code'),
        );
        $this->assertTrue($configuredForm->hasActiveRegionFields($saved));
    }

    public function test_admin_can_save_all_required_builtin_policy_from_unit_settings(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->fillForm(['builtin_field_policy' => 'all_required'])
            ->call('save')
            ->assertHasNoFormErrors();

        $draft = UnitConfiguration::query()
            ->where('unit_id', $unit->id)
            ->where('status', 'draft')
            ->firstOrFail();

        $this->assertSame('all_required', $draft->builtin_field_policy);
        $this->assertSame([], $draft->fields);
    }

    public function test_publish_saves_current_form_state_before_publishing(): void
    {
        [$unit, $staff] = $this->fixture();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(UnitRegistrationSettings::class)
            ->fillForm(['documents_enabled' => false])
            ->call('publish')
            ->assertHasNoFormErrors();

        $published = app(UnitConfigurationService::class)->current($unit->id);

        $this->assertNotNull($published);
        $this->assertFalse($published->documents_enabled);
        $this->assertSame('published', $published->status);
    }

    #[TestWith([false, false])]
    #[TestWith([false, true])]
    #[TestWith([true, false])]
    #[TestWith([true, true])]
    public function test_active_workflow_respects_payment_and_document_configuration(bool $payment, bool $documents): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $configuration = $service->save($draft, $staff, array_replace($draft->toArray(), ['payment_enabled' => $payment, 'documents_enabled' => $documents]), true);
        $registration->update(['unit_configuration_id' => $configuration->id]);
        app(RegistrationWorkflowService::class)->validateData($registration, $staff, true);

        $registration->refresh();

        $this->assertSame(
            $payment ? 'virtual_account' : ($documents ? 'documents' : 'selection'),
            $registration->current_stage,
        );
        $this->assertSame($payment, array_key_exists('payment', $registration->enabledStages()));
        $this->assertSame($documents, array_key_exists('documents', $registration->enabledStages()));

        if ($payment) {
            $this->assertNull($registration->registration_number);
            $this->assertNull($registration->applicant_card_number);
        } else {
            $this->assertNotNull($registration->registration_number);
            $this->assertNotNull($registration->applicant_card_number);
            $this->assertNotNull($registration->applicant_card_issued_at);
        }
    }

    public function test_unit_configuration_can_put_documents_before_applicant_card_and_order_form_groups(): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['payment_enabled'] = false;
        $data['workflow_blocks'] = [
            ['key' => 'documents'],
            ['key' => 'applicant_card'],
        ];
        $data['form_groups'] = [
            ['key' => 'group_health', 'label' => 'Informasi Kesehatan'],
            ['key' => 'group_additional', 'label' => 'Informasi Tambahan'],
        ];
        $data['form_layout'] = [
            ['key' => 'registration_choice'],
            ['key' => 'identity'],
            ['key' => 'group:group_health'],
            ['key' => 'parents'],
            ['key' => 'group:group_additional'],
            ['key' => 'academic_scores'],
            ['key' => 'achievements'],
        ];
        $data['fields'] = [[
            'key' => 'custom_allergy',
            'label' => 'Apakah memiliki alergi?',
            'type' => 'boolean',
            'active' => true,
            'required' => false,
            'group_key' => 'group_health',
            'group' => 'Informasi Kesehatan',
            'help' => null,
            'options' => [],
        ]];

        $published = $service->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $published->id]);

        app(RegistrationWorkflowService::class)->validateData($registration, $staff, true);
        $registration->refresh();

        $this->assertSame('documents', $registration->current_stage);
        $this->assertNotNull($registration->registration_number);
        $this->assertNull($registration->applicant_card_number);

        $stages = array_keys($registration->enabledStages());
        $this->assertLessThan(
            array_search('applicant_card', $stages, true),
            array_search('documents', $stages, true),
        );
        $this->assertSame('group_health', $published->fields[0]['group_key']);
        $this->assertSame('group:group_health', $published->form_layout[2]['key']);
    }

    public function test_workflow_block_configuration_rejects_duplicate_blocks(): void
    {
        [$unit, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['workflow_blocks'] = [
            ['key' => 'applicant_card'],
            ['key' => 'applicant_card'],
        ];

        $this->expectException(ValidationException::class);
        $service->save($draft, $staff, $data);
    }

    public function test_applying_test_configuration_before_test_stage_does_not_create_test_results(): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $service = app(UnitConfigurationService::class);

        $oldConfiguration = $service->initialize($unit);

        $test = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik',
            'code' => 'EARLY',
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
            'result_type' => 'score',
        ]);

        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['tests_enabled'] = true;
        $data['test_definitions'] = [['id' => $test->id]];
        $published = $service->save($draft, $staff, $data, true);

        $registration->update([
            'unit_configuration_id' => $oldConfiguration->id,
            'current_stage' => 'payment',
        ]);

        $result = $service->applyCurrentToEligibleActiveRegistrations($unit, $staff);

        $registration->refresh();

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['moved_to_tests']);
        $this->assertSame($published->id, $registration->unit_configuration_id);
        $this->assertSame('payment', $registration->current_stage);
        $this->assertSame(0, $registration->testResults()->count());
    }

    public function test_premature_unbooked_test_results_are_removed_by_cleanup_migration(): void
    {
        [$unit, , $registration] = $this->fixture();

        $test = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Prematur',
            'code' => 'PREMATURE',
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
            'result_type' => 'score',
        ]);

        $registration->update(['current_stage' => 'payment']);

        $result = $registration->testResults()->create([
            'admission_test_id' => $test->id,
            'status' => 'unbooked',
            'result' => 'pending',
        ]);

        $migration = require database_path('migrations/2026_09_07_152000_remove_premature_test_results.php');
        $migration->up();

        $this->assertDatabaseMissing('admission_test_results', ['id' => $result->id]);
    }

    public function test_required_test_configuration_adds_test_stage_and_can_be_applied_to_pending_selection(): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $service = app(UnitConfigurationService::class);

        $oldConfiguration = $service->initialize($unit);

        $test = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik',
            'code' => 'AKD',
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
            'result_type' => 'score',
        ]);

        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['tests_enabled'] = true;
        $data['test_definitions'] = [['id' => $test->id]];

        $published = $service->save($draft, $staff, $data, true);

        $registration->update([
            'unit_configuration_id' => $oldConfiguration->id,
            'current_stage' => 'selection',
        ]);
        Selection::firstOrCreate(
            ['registration_id' => $registration->id],
            ['decision' => 'pending'],
        );

        $result = $service->applyCurrentToEligibleActiveRegistrations($unit, $staff);

        $registration->refresh();

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['moved_to_tests']);
        $this->assertSame($published->id, $registration->unit_configuration_id);
        $this->assertSame('tests', $registration->current_stage);
        $this->assertArrayHasKey('tests', $registration->enabledStages());
        $this->assertDatabaseHas('admission_test_results', [
            'registration_id' => $registration->id,
            'admission_test_id' => $test->id,
            'status' => 'unbooked',
            'result' => 'pending',
        ]);
    }

    public function test_active_configuration_sync_skips_selection_that_already_has_a_decision(): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $service = app(UnitConfigurationService::class);

        $oldConfiguration = $service->initialize($unit);

        $test = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik',
            'code' => 'AKD',
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
            'result_type' => 'score',
        ]);

        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['tests_enabled'] = true;
        $data['test_definitions'] = [['id' => $test->id]];
        $service->save($draft, $staff, $data, true);

        $registration->update([
            'unit_configuration_id' => $oldConfiguration->id,
            'current_stage' => 'selection',
        ]);
        Selection::updateOrCreate(
            ['registration_id' => $registration->id],
            ['decision' => 'accepted', 'decided_at' => now(), 'decided_by' => $staff->id],
        );

        $result = $service->applyCurrentToEligibleActiveRegistrations($unit, $staff);

        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame($oldConfiguration->id, $registration->fresh()->unit_configuration_id);
        $this->assertSame('selection', $registration->fresh()->current_stage);
    }

    public function test_region_fields_cannot_be_published_before_region_master_is_loaded(): void
    {
        [$unit, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['fields'] = [[
            'key' => 'province_code',
            'label' => 'Provinsi',
            'type' => 'select',
            'active' => true,
            'required' => true,
            'group' => 'Alamat',
            'help' => null,
            'options' => [],
        ]];

        $this->expectException(ValidationException::class);

        $service->save($draft, $staff, $data, true);
    }

    public function test_region_fields_must_be_configured_in_parent_child_order(): void
    {
        [$unit, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['fields'] = [[
            'key' => 'village_code',
            'label' => 'Desa/Kelurahan',
            'type' => 'select',
            'active' => true,
            'required' => true,
            'group' => 'Alamat',
            'help' => null,
            'options' => [],
        ]];

        $this->expectException(ValidationException::class);

        $service->save($draft, $staff, $data, true);
    }

    public function test_custom_choices_are_validated_and_unknown_answers_are_not_saved(): void
    {
        [$unit, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['fields'] = [['key' => 'transport', 'label' => 'Transportasi', 'type' => 'select', 'active' => true, 'required' => true, 'options' => ['Jalan kaki', 'Mobil']]];
        $configuration = $service->save($draft, $staff, $data, true);
        $form = app(ConfiguredRegistrationForm::class);
        $this->assertSame(['transport' => 'Mobil'], $form->validateAnswers($configuration, ['transport' => 'Mobil', 'unauthorized' => 'value']));
        $this->expectException(ValidationException::class);
        $form->validateAnswers($configuration, ['transport' => 'Pesawat']);
    }

    public function test_boolean_custom_field_validates_branch_specific_detail_and_drops_hidden_stale_detail(): void
    {
        [$unit, $staff] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['fields'] = [[
            'key' => 'has_condition',
            'label' => 'Apakah memiliki kondisi khusus?',
            'type' => 'boolean',
            'active' => true,
            'required' => true,
            'group_key' => 'group_additional',
            'group' => 'Informasi Tambahan',
            'help' => null,
            'options' => [],
            'boolean_yes_detail_enabled' => true,
            'boolean_yes_detail_label' => 'Jelaskan kondisi khusus',
            'boolean_yes_detail_required' => true,
            'boolean_no_detail_enabled' => false,
            'boolean_no_detail_label' => 'Keterangan',
            'boolean_no_detail_required' => false,
        ]];

        $configuration = $service->save($draft, $staff, $data, true);
        $field = $configuration->fields[0];

        $this->assertTrue($field['boolean_yes_detail_enabled']);
        $this->assertSame('Jelaskan kondisi khusus', $field['boolean_yes_detail_label']);
        $this->assertTrue($field['boolean_yes_detail_required']);
        $this->assertFalse($field['boolean_no_detail_enabled']);
        $this->assertFalse($field['boolean_no_detail_required']);

        $form = app(ConfiguredRegistrationForm::class);

        try {
            $form->validateAnswers($configuration, ['has_condition' => '1']);
            $this->fail('Keterangan wajib untuk jawaban Ya seharusnya ditolak ketika kosong.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('_details.has_condition', $exception->errors());
        }

        $this->assertSame(
            [
                'has_condition' => '1',
                '_details' => ['has_condition' => 'Memerlukan pendampingan khusus.'],
            ],
            $form->validateAnswers($configuration, [
                'has_condition' => '1',
                '_details' => ['has_condition' => 'Memerlukan pendampingan khusus.'],
            ]),
        );

        $this->assertSame(
            ['has_condition' => '0'],
            $form->validateAnswers($configuration, [
                'has_condition' => '0',
                '_details' => ['has_condition' => 'Nilai lama yang harus dibuang.'],
            ]),
        );
    }

    public function test_applicant_boolean_detail_is_hidden_until_matching_answer_then_saved_and_shown_in_summary(): void
    {
        [$unit, $staff, $registration, $parent] = $this->fixture();
        $pathway = RegistrationPathway::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['fields'] = [[
            'key' => 'has_condition',
            'label' => 'Apakah memiliki kondisi khusus?',
            'type' => 'boolean',
            'active' => true,
            'required' => true,
            'group_key' => 'group_additional',
            'group' => 'Informasi Tambahan',
            'help' => 'Pilih Ya atau Tidak.',
            'options' => [],
            'boolean_yes_detail_enabled' => true,
            'boolean_yes_detail_label' => 'Jelaskan kondisi khusus',
            'boolean_yes_detail_required' => true,
            'boolean_no_detail_enabled' => false,
            'boolean_no_detail_label' => 'Keterangan',
            'boolean_no_detail_required' => false,
        ]];

        $configuration = $service->save($draft, $staff, $data, true);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        $page = Livewire::withQueryParams(['opening' => $registration->opening->uuid])
            ->test(CreateRegistration::class)
            ->assertSee('Apakah memiliki kondisi khusus?')
            ->assertDontSee('Jelaskan kondisi khusus')
            ->fillForm(['custom_answers.has_condition' => '0'])
            ->assertDontSee('Jelaskan kondisi khusus')
            ->fillForm(['custom_answers.has_condition' => '1'])
            ->assertSee('Jelaskan kondisi khusus')
            ->fillForm([
                'registration_pathway_uuid' => $pathway->uuid,
                'registrant_type' => 'self',
                'full_name' => 'Peserta Kondisional',
                'nik' => '3273010101010066',
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2020-01-01',
                'home_address' => 'Bandung',
                'parentInfo' => [
                    'father_name' => 'Ayah',
                    'mother_name' => 'Ibu',
                ],
                'custom_answers.has_condition' => '1',
            ])
            ->call('create')
            ->assertHasFormErrors(['custom_answers._details.has_condition' => 'required']);

        $page->fillForm([
            'custom_answers._details.has_condition' => 'Memerlukan pendampingan khusus.',
        ])->call('create')->assertHasNoFormErrors();

        $created = Registration::query()
            ->where('nik', '3273010101010066')
            ->firstOrFail();

        $this->assertSame('1', $created->custom_answers['has_condition']);
        $this->assertSame(
            'Memerlukan pendampingan khusus.',
            data_get($created->custom_answers, '_details.has_condition'),
        );

        $this->get('/pendaftar/status/'.$created->uuid)
            ->assertOk()
            ->assertSeeText('Apakah memiliki kondisi khusus?')
            ->assertSeeText('Ya')
            ->assertSeeText('Jelaskan kondisi khusus')
            ->assertSeeText('Memerlukan pendampingan khusus.');

        $this->assertSame($configuration->id, $created->unit_configuration_id);
    }

    public function test_disabled_payment_rejects_nonzero_opening_fee(): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $registration->opening->update(['registration_fee' => 50000]);
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $this->expectException(ValidationException::class);
        $service->save($draft, $staff, array_replace($draft->toArray(), ['payment_enabled' => false]), true);
    }

    public function test_applicant_create_form_validates_custom_field_and_pins_published_version(): void
    {
        [$unit, $staff, $registration, $parent] = $this->fixture();
        $pathway = RegistrationPathway::factory()->create(['unit_id' => $unit->id]);
        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['fields'] = [['key' => 'transport', 'label' => 'Transportasi Peserta', 'type' => 'select', 'active' => true, 'required' => true, 'options' => ['Jalan kaki', 'Mobil']]];
        $configuration = $service->save($draft, $staff, $data, true);
        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $page = Livewire::withQueryParams(['opening' => $registration->opening->uuid])->test(CreateRegistration::class)
            ->assertSee('Transportasi Peserta')
            ->fillForm(['registration_pathway_uuid' => $pathway->uuid, 'registrant_type' => 'self', 'full_name' => 'Peserta Baru', 'nik' => '3273010101010002', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2020-01-01', 'home_address' => 'Bandung', 'parentInfo' => ['father_name' => 'Ayah', 'mother_name' => 'Ibu']])
            ->call('create')->assertHasFormErrors(['custom_answers.transport' => 'required']);
        $page->fillForm(['custom_answers' => ['transport' => 'Mobil']])->call('create')->assertHasNoFormErrors();
        $created = Registration::where('nik', '3273010101010002')->firstOrFail();
        $this->assertSame($configuration->id, $created->unit_configuration_id);
        $this->assertSame(['transport' => 'Mobil'], $created->custom_answers);
        $this->get('/pendaftar/status/'.$created->uuid)->assertSeeText('Transportasi Peserta')->assertSeeText('Mobil');
    }

    public function test_configured_parent_income_select_is_rendered_and_prefilled_for_another_participant(): void
    {
        [$unit, $staff, $registration, $parent] = $this->fixture();
        $pathway = RegistrationPathway::factory()->create(['unit_id' => $unit->id]);

        $registration->parentInfo()->create([
            'father_name' => 'Ayah Lama',
            'mother_name' => 'Ibu Lama',
            'father_income' => '< 5 juta',
        ]);

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['fields'] = [[
            'key' => 'father_income',
            'label' => 'Penghasilan Ayah',
            'type' => 'select',
            'active' => true,
            'required' => true,
            'group' => null,
            'group_key' => null,
            'help' => 'Pilih rentang penghasilan.',
            'options' => ['< 5 juta', '5 - 10 juta', '10 - 20 juta', '20 juta'],
        ]];
        $configuration = $service->save($draft, $staff, $data, true);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        $page = Livewire::withQueryParams(['opening' => $registration->opening->uuid])
            ->test(CreateRegistration::class)
            ->assertFormSet([
                'unit_configuration_uuid' => $configuration->uuid,
                'parentInfo.father_income' => '< 5 juta',
            ])
            ->fillForm([
                'registration_pathway_uuid' => $pathway->uuid,
                'registrant_type' => 'parent',
                'registrant_relationship' => 'father',
                'full_name' => 'Peserta Kedua',
                'nik' => '3273010101010099',
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2020-01-01',
                'home_address' => 'Bandung',
                'parentInfo' => [
                    'father_name' => 'Ayah Lama',
                    'mother_name' => 'Ibu Lama',
                    'father_income' => '5 - 10 juta',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = Registration::query()->where('nik', '3273010101010099')->firstOrFail();

        $this->assertSame('5 - 10 juta', $created->parentInfo?->father_income);
        $this->assertSame($configuration->id, $created->unit_configuration_id);
    }

    public function test_parent_workplaces_are_configurable_prefilled_and_saved_for_additional_participant(): void
    {
        [$unit, $staff, $registration, $parent] = $this->fixture();

        $pathway = RegistrationPathway::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);
        $registration->update(['registration_pathway_id' => $pathway->id]);

        $registration->parentInfo()->create([
            'father_name' => 'Ayah Lama',
            'father_occupation' => 'Guru',
            'father_workplace' => 'SMA Taruna Bakti',
            'mother_name' => 'Ibu Lama',
            'mother_occupation' => 'Dokter',
            'mother_workplace' => 'RS Bandung',
        ]);

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $configuration = $service->save($draft, $staff, $draft->toArray(), true);

        $this->assertContains('father_workplace', ConfiguredRegistrationForm::BUILTIN_FIELDS);
        $this->assertContains('mother_workplace', ConfiguredRegistrationForm::BUILTIN_FIELDS);
        $this->assertSame(
            'Instansi / Tempat Kerja Ayah',
            ConfiguredRegistrationForm::fieldLabels()['father_workplace'],
        );
        $this->assertSame(
            'Instansi / Tempat Kerja Ibu',
            ConfiguredRegistrationForm::fieldLabels()['mother_workplace'],
        );

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $registration->opening->uuid])
            ->test(CreateRegistration::class)
            ->assertFormSet([
                'unit_configuration_uuid' => $configuration->uuid,
                'registration_pathway_uuid' => $pathway->uuid,
                'parentInfo.father_workplace' => 'SMA Taruna Bakti',
                'parentInfo.mother_workplace' => 'RS Bandung',
            ])
            ->fillForm([
                'registrant_type' => 'parent',
                'registrant_relationship' => 'father',
                'full_name' => 'Peserta Instansi',
                'nik' => '3273010101010088',
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2020-01-01',
                'home_address' => 'Bandung',
                'parentInfo' => [
                    'father_name' => 'Ayah Lama',
                    'father_occupation' => 'Guru',
                    'father_workplace' => 'SMP Taruna Bakti',
                    'mother_name' => 'Ibu Lama',
                    'mother_occupation' => 'Dokter',
                    'mother_workplace' => 'RS Hasan Sadikin',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = Registration::query()
            ->where('nik', '3273010101010088')
            ->with('parentInfo')
            ->firstOrFail();

        $this->assertSame('SMP Taruna Bakti', $created->parentInfo?->father_workplace);
        $this->assertSame('RS Hasan Sadikin', $created->parentInfo?->mother_workplace);
    }

    public function test_single_registration_pathway_is_selected_automatically_and_locked(): void
    {
        [$unit, , $registration, $parent] = $this->fixture();

        $pathway = RegistrationPathway::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $registration->opening->uuid])
            ->test(CreateRegistration::class)
            ->assertFormSet([
                'registration_pathway_uuid' => $pathway->uuid,
            ])
            ->assertFormFieldIsDisabled('registration_pathway_uuid');
    }

    public function test_additional_participant_form_keeps_custom_fields_and_academic_scores(): void
    {
        [$unit, $staff, $registration, $parent] = $this->fixture();
        $pathway = RegistrationPathway::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
        ]);
        $registration->update(['registration_pathway_id' => $pathway->id]);

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['fields'] = [[
            'key' => 'custom_note',
            'label' => 'Catatan Tambahan',
            'type' => 'text',
            'active' => true,
            'required' => false,
            'group' => 'Informasi Tambahan',
            'group_key' => 'group_additional',
            'help' => null,
            'options' => [],
        ]];
        $data['academic_scores_enabled'] = true;
        $data['academic_score_settings'] = [
            'required' => true,
            'min_score' => 0,
            'max_score' => 100,
            'pathway_uuids' => [$pathway->uuid],
            'grades' => [['key' => 'vii', 'label' => 'Kelas VII']],
            'subjects' => [['key' => 'matematika', 'label' => 'Matematika']],
            'assessments' => [[
                'key' => 'rapor_s1',
                'label' => 'Nilai Rapor Semester 1',
                'grade_keys' => ['vii'],
            ]],
        ];

        $configuration = $service->save($draft, $staff, $data, true);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $registration->opening->uuid])
            ->test(CreateRegistration::class)
            ->assertFormSet([
                'registration_pathway_uuid' => $pathway->uuid,
                'unit_configuration_uuid' => $configuration->uuid,
            ])
            ->assertSee('Catatan Tambahan')
            ->assertSee('Data Nilai')
            ->assertSee('Nilai Rapor Semester 1');
    }

    public function test_presentation_metadata_follows_latest_published_version_without_changing_pinned_rules(): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $service = app(UnitConfigurationService::class);

        $oldDraft = $service->draft($unit, $staff);
        $oldData = $oldDraft->toArray();
        $oldData['fields'] = [[
            'key' => 'custom_note',
            'label' => 'Catatan Lama',
            'type' => 'text',
            'active' => true,
            'required' => false,
            'group' => 'Informasi Tambahan',
            'group_key' => 'group_additional',
            'help' => 'Petunjuk lama.',
            'options' => [],
        ]];
        $oldData['re_registration_requirements'] = [[
            'key' => 'confirmation',
            'label' => 'Konfirmasi Lama',
            'type' => 'checklist',
            'instructions' => 'Petunjuk daftar ulang lama.',
            'active' => true,
            'required' => true,
        ]];

        $old = $service->save($oldDraft, $staff, $oldData, true);
        $registration->update(['unit_configuration_id' => $old->id]);

        $newDraft = $service->draft($unit, $staff);
        $newData = $newDraft->toArray();
        $newData['fields'] = [[
            'key' => 'custom_note',
            'label' => 'Catatan Terbaru',
            'type' => 'text',
            'active' => true,
            'required' => true,
            'group' => 'Informasi Tambahan',
            'group_key' => 'group_additional',
            'help' => 'Petunjuk terbaru untuk pendaftar.',
            'options' => [],
        ]];
        $newData['re_registration_requirements'] = [[
            'key' => 'confirmation',
            'label' => 'Konfirmasi Terbaru',
            'type' => 'checklist',
            'instructions' => 'Petunjuk daftar ulang terbaru.',
            'active' => true,
            'required' => false,
        ]];

        $service->save($newDraft, $staff, $newData, true);

        $field = collect(app(ConfiguredRegistrationForm::class)->presentationFields($old))
            ->firstWhere('key', 'custom_note');

        $this->assertSame('Catatan Terbaru', $field['label']);
        $this->assertSame('Petunjuk terbaru untuk pendaftar.', $field['help']);
        $this->assertFalse($field['required']);

        $reRegistration = collect($registration->fresh()->reRegistrationRequirements())
            ->firstWhere('key', 'confirmation');

        $this->assertSame('Konfirmasi Terbaru', $reRegistration['label']);
        $this->assertSame('Petunjuk daftar ulang terbaru.', $reRegistration['instructions']);
        $this->assertTrue($reRegistration['required']);
    }

    public function test_rt_and_rw_are_default_registration_fields_and_preserve_leading_zeroes(): void
    {
        [$unit, $staff, $registration, $parent] = $this->fixture();
        $pathway = RegistrationPathway::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $service->save($draft, $staff, $draft->toArray(), true);

        $this->assertContains('rt', ConfiguredRegistrationForm::BUILTIN_FIELDS);
        $this->assertContains('rw', ConfiguredRegistrationForm::BUILTIN_FIELDS);
        $this->assertSame('RT', ConfiguredRegistrationForm::fieldLabels()['rt']);
        $this->assertSame('RW', ConfiguredRegistrationForm::fieldLabels()['rw']);

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $registration->opening->uuid])
            ->test(CreateRegistration::class)
            ->assertSee('Alamat Rumah')
            ->assertSee('RT')
            ->assertSee('RW')
            ->fillForm([
                'registration_pathway_uuid' => $pathway->uuid,
                'registrant_type' => 'self',
                'full_name' => 'Peserta RT RW',
                'nik' => '3273010101010077',
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2020-01-01',
                'home_address' => 'Jalan Contoh 1',
                'rt' => '001',
                'rw' => '007',
                'parentInfo' => [
                    'father_name' => 'Ayah',
                    'mother_name' => 'Ibu',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = Registration::query()->where('nik', '3273010101010077')->firstOrFail();

        $this->assertSame('001', $created->rt);
        $this->assertSame('007', $created->rw);
    }

    public function test_admin_can_end_workflow_after_tests_and_customize_applicant_progress_and_message(): void
    {
        [$unit, $staff, $registration, $parent] = $this->fixture();

        $test = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akhir',
            'code' => 'FINAL-TEST',
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
            'result_type' => 'score',
        ]);

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['tests_enabled'] = true;
        $data['test_definitions'] = [['id' => $test->id]];
        $data['completion_after_stage'] = 'tests';
        $data['applicant_visible_stages'] = ['data_validation', 'documents', 'tests', 'completed'];
        $data['completion_title'] = 'Tahapan Pendaftaran Selesai';
        $data['completion_message'] = 'Terima kasih. Informasi berikutnya akan disampaikan oleh sekolah.';

        $configuration = $service->save($draft, $staff, $data, true);

        $registration->update([
            'unit_configuration_id' => $configuration->id,
            'current_stage' => 'tests',
        ]);

        $result = AdmissionTestResult::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $test->id,
            'status' => 'unbooked',
            'result' => 'pending',
        ]);

        app(RegistrationWorkflowService::class)->recordTestResult(
            $result,
            $staff,
            [
                'status' => 'completed',
                'result' => 'passed',
                'score' => 88,
            ],
        );

        $registration->refresh();

        $this->assertSame('completed', $registration->current_stage);
        $this->assertDatabaseMissing('selections', ['registration_id' => $registration->id]);
        $this->assertSame(
            ['data_validation', 'documents', 'tests', 'completed'],
            array_keys($registration->progressStages()),
        );
        $this->assertSame('Tahapan Pendaftaran Selesai', $registration->completionTitle());
        $this->assertSame(
            'Terima kasih. Informasi berikutnya akan disampaikan oleh sekolah.',
            $registration->completionMessage(),
        );

        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        $this->get('/pendaftar/status/'.$registration->uuid)
            ->assertOk()
            ->assertSeeText('Tahapan Pendaftaran Selesai')
            ->assertSeeText('Terima kasih. Informasi berikutnya akan disampaikan oleh sekolah.')
            ->assertDontSeeText('Seleksi Peserta')
            ->assertDontSeeText('Pengumuman');
    }

    public function test_a_newly_published_version_rejects_an_already_open_form(): void
    {
        [$unit, $staff, $registration, $parent] = $this->fixture();
        $pathway = RegistrationPathway::factory()->create(['unit_id' => $unit->id]);
        $service = app(UnitConfigurationService::class);
        $service->initialize($unit);
        $this->actingAs($parent);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $page = Livewire::withQueryParams(['opening' => $registration->opening->uuid])->test(CreateRegistration::class)
            ->fillForm(['registration_pathway_uuid' => $pathway->uuid, 'registrant_type' => 'self', 'full_name' => 'Peserta Baru', 'nik' => '3273010101010002', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2020-01-01', 'home_address' => 'Bandung', 'parentInfo' => ['father_name' => 'Ayah', 'mother_name' => 'Ibu']]);
        $draft = $service->draft($unit, $staff);
        $service->save($draft, $staff, $draft->toArray(), true);
        $page->call('create')->assertHasErrors(['unit_configuration_uuid']);
        $this->assertDatabaseMissing('registrations', ['nik' => '3273010101010002']);
    }

    private function fixture(): array
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'SD Test', 'code' => 'SD', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'admin_unit', 'unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');
        $parent = User::factory()->create(['is_active' => true]);
        $parent->assignRole('pendaftar');
        $opening = RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'status' => 'open', 'registration_fee' => 0]);
        $registration = Registration::create(['user_id' => $parent->id, 'unit_id' => $unit->id, 'registration_opening_id' => $opening->id, 'full_name' => 'Peserta Test', 'nik' => '3273010101010001', 'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2020-01-01', 'home_address' => 'Bandung', 'current_stage' => 'data_validation']);

        return [$unit, $staff, $registration, $parent];
    }
}
