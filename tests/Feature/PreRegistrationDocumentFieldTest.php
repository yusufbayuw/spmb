<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\ConfiguredRegistrationForm;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PreRegistrationDocumentFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_pre_registration_document_field_template_and_answer_are_downloadable_privately(): void
    {
        Storage::fake('applicant-private');
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SMA Test',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $admin->assignRole('admin_unit');

        $applicant = User::factory()->create(['is_active' => true]);
        $applicant->assignRole('pendaftar');

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);

        $templatePath = 'templates/'.$unit->id.'/surat-pernyataan.pdf';
        Storage::disk('applicant-private')->put(
            $templatePath,
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF",
        );

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $admin);
        $data = $draft->toArray();
        $data['fields'] = [[
            'key' => 'consent_letter',
            'label' => 'Surat Pernyataan',
            'type' => 'file',
            'active' => true,
            'required' => true,
            'group' => 'Informasi Tambahan',
            'group_key' => 'group_additional',
            'help' => 'Unduh template, isi, lalu unggah kembali.',
            'options' => [],
            'formats' => ['pdf'],
            'template_path' => $templatePath,
        ]];

        $configuration = $service->save($draft, $admin, $data, true);

        $this->actingAs($applicant)
            ->get(route('registration.form-field-template', [$configuration, 'consent_letter']))
            ->assertDownload('surat-pernyataan.pdf');

        $answerPath = 'pre-registration/'.$applicant->id.'/surat-pernyataan.pdf';
        Storage::disk('applicant-private')->put(
            $answerPath,
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF",
        );

        $answers = app(ConfiguredRegistrationForm::class)->validateAnswers(
            $configuration,
            ['consent_letter' => $answerPath],
        );

        $this->assertSame($answerPath, $answers['consent_letter']);

        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'unit_configuration_id' => $configuration->id,
            'registration_opening_id' => $opening->id,
            'full_name' => 'Peserta Test',
            'nik' => '3273010101010001',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'custom_answers' => $answers,
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);

        $this->get(route('files.applicant.registration-custom-field', [
            'registration' => $registration,
            'key' => 'consent_letter',
            'download' => 1,
        ]))->assertDownload('surat-pernyataan.pdf');
    }

    public function test_pre_registration_document_answer_cannot_reference_another_users_private_folder(): void
    {
        Storage::fake('applicant-private');
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SMA Test',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $admin->assignRole('admin_unit');

        $applicant = User::factory()->create(['is_active' => true]);
        $applicant->assignRole('pendaftar');
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('pendaftar');

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $admin);
        $data = $draft->toArray();
        $data['fields'] = [[
            'key' => 'consent_letter',
            'label' => 'Surat Pernyataan',
            'type' => 'file',
            'active' => true,
            'required' => true,
            'group' => 'Informasi Tambahan',
            'group_key' => 'group_additional',
            'help' => null,
            'options' => [],
            'formats' => ['pdf'],
            'template_path' => null,
        ]];

        $configuration = $service->save($draft, $admin, $data, true);

        $otherPath = 'pre-registration/'.$other->id.'/surat.pdf';
        Storage::disk('applicant-private')->put(
            $otherPath,
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF",
        );

        $this->actingAs($applicant);

        $this->expectException(ValidationException::class);

        app(ConfiguredRegistrationForm::class)->validateAnswers(
            $configuration,
            ['consent_letter' => $otherPath],
        );
    }
}
