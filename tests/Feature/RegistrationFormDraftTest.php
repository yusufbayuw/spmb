<?php

namespace Tests\Feature;

use App\Models\RegistrationFormDraft;
use App\Models\Unit;
use App\Models\User;
use App\Services\RegistrationFormDraftService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationFormDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_draft_is_encrypted_in_database_and_does_not_create_registration(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create(['name' => 'Unit Draft', 'code' => 'UD', 'institution_type' => 'school', 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('pendaftar');
        $opening = \App\Models\RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'status' => 'open', 'registration_fee' => 0]);
        $configuration = app(UnitConfigurationService::class)->initialize($unit);

        $draft = app(RegistrationFormDraftService::class)->save(
            $user, $opening, $configuration,
            [
                'full_name' => 'Nama Sensitif',
                'nik' => '3273010101010001',
                'unit_uuid' => 'forged-unit',
                'privacyConsentUuid' => 'forged-token',
                'custom_answers' => ['upload_field' => 'private/test.pdf'],
                'parentInfo' => ['father_name' => 'Ayah', 'user_id' => 99],
            ], 0,
        );

        $this->assertSame('Nama Sensitif', $draft->payload['full_name']);
        $this->assertArrayNotHasKey('unit_uuid', $draft->payload);
        $this->assertArrayNotHasKey('privacyConsentUuid', $draft->payload);
        $this->assertArrayNotHasKey('user_id', $draft->payload['parentInfo']);
        $this->assertSame(1, RegistrationFormDraft::query()->count());
        $this->assertSame(0, \App\Models\Registration::query()->count());
        $this->assertStringNotContainsString(
            'Nama Sensitif',
            \Illuminate\Support\Facades\DB::table('registration_form_drafts')->value('payload'),
        );
    }

    public function test_boolean_details_are_preserved_and_document_paths_are_not_saved(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'Unit Draft', 'code' => 'UD', 'institution_type' => 'school', 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('pendaftar');
        $opening = \App\Models\RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'status' => 'open', 'registration_fee' => 0]);
        $configuration = \App\Models\UnitConfiguration::create([
            ...app(UnitConfigurationService::class)->defaults($unit),
            'unit_id' => $unit->id,
            'version' => 1,
            'status' => 'published',
            'legacy' => true,
            'published_at' => now(),
            'fields' => [
                ['key' => 'medical_declaration', 'label' => 'Keterangan', 'type' => 'boolean', 'active' => true, 'required' => false],
                ['key' => 'medical_document', 'label' => 'Bukti', 'type' => 'file', 'active' => true, 'required' => false],
            ],
        ]);

        $saved = app(RegistrationFormDraftService::class)->save($user, $opening, $configuration, [
            'custom_answers' => [
                'medical_declaration' => '1',
                '_details' => ['medical_declaration' => 'Butuh pendampingan', 'unrelated' => 'rahasia'],
                'medical_document' => 'pre-registration/1/medical.pdf',
            ],
        ], 0);

        $this->assertSame('Butuh pendampingan', $saved->payload['custom_answers']['_details']['medical_declaration']);
        $this->assertArrayNotHasKey('unrelated', $saved->payload['custom_answers']['_details']);
        $this->assertArrayNotHasKey('medical_document', $saved->payload['custom_answers']);
    }


    public function test_applicant_can_save_resume_and_complete_registration_without_draft_surviving(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'Unit Draft', 'code' => 'UD', 'institution_type' => 'school', 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('pendaftar');
        $opening = \App\Models\RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'status' => 'open', 'registration_fee' => 0]);
        app(UnitConfigurationService::class)->initialize($unit);
        $pathway = \App\Models\RegistrationPathway::factory()->create(['unit_id' => $unit->id]);
        $this->actingAs($user);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('pendaftar'));

        $page = \Livewire\Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(\App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration::class)
            ->setActionData(['accepted' => true])->callMountedAction()
            ->fillForm([
                'registration_pathway_uuid' => $pathway->uuid,
                'registrant_type' => 'self',
                'full_name' => 'Draft Menjadi Final',
                'nik' => '3273010101010018',
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2020-01-01',
                'home_address' => 'Bandung',
                'parentInfo' => ['father_name' => 'Ayah', 'mother_name' => 'Ibu'],
            ])->call('saveRegistrationDraft')->assertHasNoErrors();

        $this->assertDatabaseCount('registration_form_drafts', 1);
        $this->assertDatabaseCount('registrations', 0);

        $fresh = \Livewire\Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(\App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration::class)
            ->setActionData(['accepted' => true])->callMountedAction()
            ->call('resumeRegistrationDraft')->assertHasNoErrors();
        $this->assertSame('Draft Menjadi Final', $fresh->get('data.full_name'));

        $fresh->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('registration_form_drafts', 0);
    }

    public function test_revision_conflict_does_not_overwrite_a_newer_draft(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'Unit Draft', 'code' => 'UD', 'institution_type' => 'school', 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->assignRole('pendaftar');
        $opening = \App\Models\RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'status' => 'open', 'registration_fee' => 0]);
        $configuration = app(UnitConfigurationService::class)->initialize($unit);
        $service = app(RegistrationFormDraftService::class);
        $service->save($user, $opening, $configuration, ['full_name' => 'Versi Awal'], 0);

        try {
            $service->save($user, $opening, $configuration, ['full_name' => 'Tab Lama'], 0);
            $this->fail('Seharusnya menolak draft dari tab lama.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('draft', $exception->errors());
        }

        $this->assertSame('Versi Awal', $service->find($user, $opening, $configuration)->payload['full_name']);
    }
}
