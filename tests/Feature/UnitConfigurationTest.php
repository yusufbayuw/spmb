<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\UnitRegistrationSettings;
use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\AdmissionTest;
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
            ->call('showPreview')
            ->assertHasNoFormErrors()
            ->assertSee('Nama Lengkap');
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
