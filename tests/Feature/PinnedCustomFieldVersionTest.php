<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationResource\Pages\EditRegistration;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\ConfiguredRegistrationForm;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PinnedCustomFieldVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_choice_type_does_not_change_a_previous_registration_editor_or_answer(): void
    {
        [$registration, $old, $current, $staff] = $this->fixtureWithTwoFormVersions();

        $this->assertSame($old->id, $registration->unit_configuration_id);
        $this->assertSame('B', $registration->custom_answers['custom_choice']);

        $editor = app(ConfiguredRegistrationForm::class);
        $oldFields = $editor->staffEditableCustomFields($registration->fresh(['configuration']));
        $oldChoice = collect($oldFields)->first(
            fn ($field): bool => $field->getName() === 'custom_answers.custom_choice',
        );

        $this->assertInstanceOf(Select::class, $oldChoice);
        $this->assertFalse($oldChoice->isMultiple());
        $this->assertSame(['A' => 'A', 'B' => 'B'], $oldChoice->getOptions());

        $newRegistration = Registration::create([
            'user_id' => $registration->user_id,
            'unit_id' => $registration->unit_id,
            'registration_opening_id' => $registration->registration_opening_id,
            'registration_pathway_id' => $registration->registration_pathway_id,
            'unit_configuration_id' => $current->id,
            'full_name' => 'Pendaftaran Baru',
            'nik' => '3273010101010051',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2015-02-01',
            'home_address' => 'Bandung',
            'custom_answers' => ['custom_choice' => ['A', 'C']],
        ]);

        $newChoice = collect($editor->staffEditableCustomFields($newRegistration))
            ->first(fn ($field): bool => $field->getName() === 'custom_answers.custom_choice');

        $this->assertInstanceOf(Select::class, $newChoice);
        $this->assertTrue($newChoice->isMultiple());
        $this->assertSame(['A' => 'A', 'B' => 'B', 'C' => 'C'], $newChoice->getOptions());
        $this->assertSame(['A', 'C'], $newRegistration->custom_answers['custom_choice']);
    }

    public function test_mass_configuration_apply_skips_registration_with_changed_choice_schema(): void
    {
        [$registration, $old, $current, $staff] = $this->fixtureWithTwoFormVersions();
        $result = app(UnitConfigurationService::class)
            ->applyCurrentToEligibleActiveRegistrations($registration->unit, $staff);

        $this->assertSame(0, $result['updated']);
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
        $this->assertSame($old->id, $registration->fresh()->unit_configuration_id);
        $this->assertSame('B', $registration->fresh()->custom_answers['custom_choice']);
        $this->assertSame($current->id, app(UnitConfigurationService::class)->current($registration->unit_id)->id);
    }

    public function test_staff_can_edit_old_choice_without_touching_private_uploads_or_unknown_answers(): void
    {
        [$registration, $old, , $staff] = $this->fixtureWithTwoFormVersions();

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditRegistration::class, ['record' => $registration->getRouteKey()])
            ->assertSee('Perbarui Jawaban Tambahan')
            ->assertFormSet(['custom_answers.custom_choice' => 'B'])
            ->fillForm(['custom_answers.custom_choice' => 'A'])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $registration->fresh();
        $this->assertSame($old->id, $saved->unit_configuration_id);
        $this->assertSame('A', $saved->custom_answers['custom_choice']);
        $this->assertSame('Dari versi sebelumnya', $saved->custom_answers['old_removed_question']);
        $this->assertSame(
            'pre-registration/'.$registration->user_id.'/legacy-document.pdf',
            $saved->custom_answers['legacy_file'],
        );
    }

    /** @return array{Registration, \App\Models\UnitConfiguration, \App\Models\UnitConfiguration, User} */
    private function fixtureWithTwoFormVersions(): array
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SMP Uji Versi',
            'code' => 'SMP-PIN',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('admin_unit');
        $applicant = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $applicant->assignRole('pendaftar');

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);
        $pathway = RegistrationPathway::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Reguler',
            'is_active' => true,
        ]);
        $configurationService = app(UnitConfigurationService::class);
        $draft = $configurationService->draft($unit, $staff);
        $configData = $draft->toArray();
        $configData['fields'] = [
            [
                'key' => 'custom_choice',
                'label' => 'Pilihan Lama',
                'type' => 'select',
                'active' => true,
                'required' => false,
                'group' => 'Informasi Tambahan',
                'group_key' => 'group_additional',
                'options' => ['A', 'B'],
            ],
            [
                'key' => 'legacy_file',
                'label' => 'Arsip Dokumen',
                'type' => 'file',
                'active' => true,
                'required' => false,
                'group' => 'Informasi Tambahan',
                'group_key' => 'group_additional',
                'formats' => ['pdf'],
            ],
        ];
        $old = $configurationService->save($draft, $staff, $configData, true);

        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway->id,
            'unit_configuration_id' => $old->id,
            'full_name' => 'Peserta Lama',
            'nik' => '3273010101010050',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2015-01-01',
            'home_address' => 'Bandung',
            'current_stage' => 'data_validation',
            'custom_answers' => [
                'custom_choice' => 'B',
                'legacy_file' => 'pre-registration/'.$applicant->id.'/legacy-document.pdf',
                'old_removed_question' => 'Dari versi sebelumnya',
            ],
        ]);

        $draft = $configurationService->draft($unit, $staff);
        $newData = $draft->toArray();
        $newData['fields'][0]['type'] = 'multiselect';
        $newData['fields'][0]['label'] = 'Pilihan Baru';
        $newData['fields'][0]['options'] = ['A', 'B', 'C'];
        $current = $configurationService->save($draft, $staff, $newData, true);

        return [$registration, $old, $current, $staff];
    }
}
