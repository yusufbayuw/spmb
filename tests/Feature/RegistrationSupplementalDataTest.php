<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\RegistrationSupplementalDataService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $staff = User::factory()->create(['role' => 'tu', 'unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('tu');
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
