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
        $configuration = app(UnitConfigurationService::class)->initialize($unit);
        $configuration->update(['fields' => [
            ['key' => 'medical_declaration', 'label' => 'Keterangan', 'type' => 'boolean', 'active' => true, 'required' => false],
            ['key' => 'medical_document', 'label' => 'Bukti', 'type' => 'file', 'active' => true, 'required' => false],
        ]]);

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
