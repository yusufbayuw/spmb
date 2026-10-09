<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantFileStorage;
use App\Services\RegistrationSupplementalDataService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationSupplementalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_scores_and_achievements_are_off_by_default_and_can_be_saved(): void
    {
        [$unit, $staff, $registration, $pathway] = $this->fixture();
        $configurationService = app(UnitConfigurationService::class);
        $initial = $configurationService->initialize($unit);

        $this->assertFalse($initial->academic_scores_enabled);
        $this->assertFalse($initial->achievements_enabled);

        $draft = $configurationService->draft($unit, $staff);
        $data = $draft->toArray();
        $data['academic_scores_enabled'] = true;
        $data['academic_score_settings'] = [
            'required' => true,
            'min_score' => 0,
            'max_score' => 100,
            'pathway_uuids' => [$pathway->uuid],
            'grades' => [['key' => 'vii', 'label' => 'Kelas VII']],
            'subjects' => [['key' => 'matematika', 'label' => 'Matematika']],
            'assessments' => [['key' => 'rapor_s1', 'label' => 'Rapor S-1']],
        ];
        $data['achievements_enabled'] = true;
        $data['achievement_settings'] = [
            'required' => true,
            'max_entries' => 3,
            'pathway_uuids' => [$pathway->uuid],
            'levels' => ['Provinsi', 'Nasional'],
        ];
        $configuration = $configurationService->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);

        $service = app(RegistrationSupplementalDataService::class);
        $scores = $service->validateAcademicScores($configuration, $pathway->uuid, [
            'vii' => ['matematika' => ['rapor_s1' => 91]],
        ]);
        $achievements = $service->validateAchievements($configuration, $pathway->uuid, [[
            'title' => 'Olimpiade Matematika',
            'level' => 'Nasional',
            'year' => 2026,
        ]]);

        $service->sync($registration, $scores, $achievements);

        $this->assertDatabaseHas('registration_academic_scores', [
            'registration_id' => $registration->id,
            'score' => 91,
        ]);
        $this->assertDatabaseHas('registration_achievements', [
            'registration_id' => $registration->id,
            'level' => 'Nasional',
        ]);
    }

    public function test_other_pathway_does_not_receive_scoped_fields(): void
    {
        [$unit, $staff, , $prestasi] = $this->fixture();
        $reguler = RegistrationPathway::factory()->create(['unit_id' => $unit->id, 'name' => 'Reguler']);
        $configurationService = app(UnitConfigurationService::class);
        $draft = $configurationService->draft($unit, $staff);
        $data = $draft->toArray();
        $data['academic_scores_enabled'] = true;
        $data['academic_score_settings'] = [
            'required' => true,
            'min_score' => 0,
            'max_score' => 100,
            'pathway_uuids' => [$prestasi->uuid],
            'grades' => [['key' => 'vii', 'label' => 'Kelas VII']],
            'subjects' => [['key' => 'matematika', 'label' => 'Matematika']],
            'assessments' => [['key' => 'rapor_s1', 'label' => 'Rapor S-1']],
        ];
        $configuration = $configurationService->save($draft, $staff, $data, true);

        $service = app(RegistrationSupplementalDataService::class);

        $this->assertFalse($service->featureApplies(true, $configuration->academic_score_settings, $reguler->uuid));
        $this->assertSame([], $service->validateAcademicScores($configuration, $reguler->uuid, [
            'vii' => ['matematika' => ['rapor_s1' => 99]],
        ]));
    }

    public function test_score_components_can_apply_only_to_selected_grades(): void
    {
        [$unit, $staff, , $pathway] = $this->fixture();
        $configurationService = app(UnitConfigurationService::class);
        $draft = $configurationService->draft($unit, $staff);
        $data = $draft->toArray();
        $data['academic_scores_enabled'] = true;
        $data['academic_score_settings'] = [
            'required' => true,
            'min_score' => 0,
            'max_score' => 100,
            'pathway_uuids' => [$pathway->uuid],
            'grades' => [
                ['key' => 'vii', 'label' => 'Kelas VII'],
                ['key' => 'viii', 'label' => 'Kelas VIII'],
                ['key' => 'ix', 'label' => 'Kelas IX'],
            ],
            'subjects' => [
                ['key' => 'matematika', 'label' => 'Matematika'],
            ],
            'assessments' => [
                ['key' => 'semester_1', 'label' => 'Semester 1', 'grade_keys' => []],
                ['key' => 'semester_2', 'label' => 'Semester 2', 'grade_keys' => ['vii', 'viii']],
            ],
        ];

        $configuration = $configurationService->save($draft, $staff, $data, true);
        $service = app(RegistrationSupplementalDataService::class);

        $this->assertSame(
            ['semester_1', 'semester_2'],
            collect($service->assessmentsForGrade($configuration->academic_score_settings, 'vii'))->pluck('key')->all(),
        );
        $this->assertSame(
            ['semester_1'],
            collect($service->assessmentsForGrade($configuration->academic_score_settings, 'ix'))->pluck('key')->all(),
        );

        $rows = $service->validateAcademicScores($configuration, $pathway->uuid, [
            'vii' => ['matematika' => ['semester_1' => 90, 'semester_2' => 91]],
            'viii' => ['matematika' => ['semester_1' => 92, 'semester_2' => 93]],
            'ix' => ['matematika' => ['semester_1' => 94]],
        ]);

        $this->assertCount(5, $rows);
        $this->assertFalse(collect($rows)->contains(
            fn (array $row): bool => $row['grade_key'] === 'ix' && $row['assessment_key'] === 'semester_2',
        ));
    }

    public function test_legacy_achievement_configuration_shows_optional_certificate_upload_by_default(): void
    {
        $configuration = new \App\Models\UnitConfiguration([
            'achievement_settings' => [
                'levels' => ['Nasional'],
            ],
        ]);

        $this->assertSame(
            \App\Models\UnitConfiguration::ACHIEVEMENT_CERTIFICATE_MODE_OPTIONAL,
            $configuration->achievementCertificateMode(),
        );
    }

    public function test_required_achievement_certificate_is_private_preserved_and_replaced_safely(): void
    {
        [$unit, $staff, $registration, $pathway, $applicant] = $this->fixture();

        Storage::fake(ApplicantFileStorage::PRIVATE_DISK);
        Storage::fake(ApplicantFileStorage::LEGACY_PUBLIC_DISK);

        $configurationService = app(UnitConfigurationService::class);
        $draft = $configurationService->draft($unit, $staff);
        $data = $draft->toArray();
        $data['achievements_enabled'] = true;
        $data['achievement_settings'] = [
            'required' => true,
            'max_entries' => 3,
            'pathway_uuids' => [$pathway->uuid],
            'levels' => ['Nasional'],
            'certificate_mode' => 'required',
        ];
        $configuration = $configurationService->save($draft, $staff, $data, true);
        $registration->update(['unit_configuration_id' => $configuration->id]);

        $firstPath = 'pre-registration/'.$applicant->id.'/achievements/sertifikat-1.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($firstPath, "%PDF-1.4\nsertifikat pertama");

        $service = app(RegistrationSupplementalDataService::class);
        $validated = $service->validateAchievements($configuration, $pathway->uuid, [[
            'title' => 'Olimpiade Fisika',
            'level' => 'Nasional',
            'certificate_path' => $firstPath,
            'certificate_original_name' => 'sertifikat.pdf',
        ]], $registration);

        $service->sync($registration, [], $validated);
        $achievement = $registration->achievements()->firstOrFail();

        $this->assertSame($firstPath, $achievement->certificate_path);
        $state = $service->achievementsFormState($registration->fresh());
        $this->assertArrayNotHasKey('certificate_path', $state[0]);
        $this->assertArrayNotHasKey('certificate_existing', $state[0]);

        $preserved = $service->validateAchievements($configuration, $pathway->uuid, [[
            'uuid' => $achievement->uuid,
            'title' => 'Olimpiade Fisika - Revisi',
            'level' => 'Nasional',
        ]], $registration->fresh());
        $service->sync($registration->fresh(), [], $preserved);

        $this->assertSame($firstPath, $achievement->fresh()->certificate_path);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertExists($firstPath);

        $secondPath = 'pre-registration/'.$applicant->id.'/achievements/sertifikat-2.pdf';
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->put($secondPath, "%PDF-1.4\nsertifikat pengganti");

        $replacement = $service->validateAchievements($configuration, $pathway->uuid, [[
            'uuid' => $achievement->uuid,
            'title' => 'Olimpiade Fisika - Revisi',
            'level' => 'Nasional',
            'certificate_path' => $secondPath,
            'certificate_original_name' => 'sertifikat-baru.pdf',
        ]], $registration->fresh());
        $service->sync($registration->fresh(), [], $replacement);

        $this->assertSame($secondPath, $achievement->fresh()->certificate_path);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertMissing($firstPath);
        Storage::disk(ApplicantFileStorage::PRIVATE_DISK)->assertExists($secondPath);
    }

    public function test_existing_registration_only_updates_configuration_during_data_validation(): void
    {
        [$unit, $staff, $registration] = $this->fixture();
        $service = app(UnitConfigurationService::class);
        $old = $service->initialize($unit);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['academic_scores_enabled'] = true;
        $data['academic_score_settings'] = [
            'required' => false,
            'min_score' => 0,
            'max_score' => 100,
            'pathway_uuids' => [],
            'grades' => [['key' => 'vii', 'label' => 'Kelas VII']],
            'subjects' => [['key' => 'matematika', 'label' => 'Matematika']],
            'assessments' => [['key' => 'rapor_s1', 'label' => 'Rapor S-1']],
        ];
        $published = $service->save($draft, $staff, $data, true);

        $registration->update(['unit_configuration_id' => $old->id, 'current_stage' => 'payment']);
        $result = $service->applyCurrentToEligibleActiveRegistrations($unit, $staff);

        $this->assertSame(0, $result['updated']);
        $this->assertSame($old->id, $registration->fresh()->unit_configuration_id);

        $registration->update(['current_stage' => 'data_validation']);
        $result = $service->applyCurrentToEligibleActiveRegistrations($unit, $staff);

        $this->assertSame(1, $result['updated']);
        $this->assertSame($published->id, $registration->fresh()->unit_configuration_id);
    }

    private function fixture(): array
    {
        $this->seed(ShieldSeeder::class);
        $unit = Unit::create(['name' => 'SMP Test', 'code' => 'SMP', 'institution_type' => 'school', 'is_active' => true]);
        $staff = User::factory()->create(['role' => 'admin_unit', 'unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');
        $applicant = User::factory()->create(['is_active' => true]);
        $applicant->assignRole('pendaftar');
        $opening = RegistrationOpening::create(['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'status' => 'open', 'registration_fee' => 0]);
        $pathway = RegistrationPathway::factory()->create(['unit_id' => $unit->id, 'name' => 'Prestasi']);
        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway->id,
            'full_name' => 'Peserta Test',
            'nik' => '3273010101010001',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2012-01-01',
            'home_address' => 'Bandung',
            'current_stage' => 'data_validation',
        ]);

        return [$unit, $staff, $registration, $pathway, $applicant];
    }
}
