<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\TestBooking;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\UnitConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdmissionTestProgramScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_program_and_legacy_single_program_scopes(): void
    {
        [$unit, $first, $second, $third] = $this->programs();

        $shared = AdmissionTest::create([
            'unit_id' => $unit->id, 'name' => 'Tes Bersama',
            'study_program_ids' => [$first->id, $second->id],
            'is_active' => true, 'is_required' => true,
        ]);

        $legacy = AdmissionTest::create([
            'unit_id' => $unit->id, 'name' => 'Tes Khusus Lama',
            'study_program_id' => $first->id,
            'is_active' => true, 'is_required' => true,
        ]);

        $this->assertTrue($shared->appliesToStudyProgram($first->id));
        $this->assertTrue($shared->appliesToStudyProgram($second->id));
        $this->assertFalse($shared->appliesToStudyProgram($third->id));
        $this->assertSame([$first->id], $legacy->effectiveProgramIds());

        $one = $this->registration($unit, $first, '3273010101019001');
        $two = $this->registration($unit, $second, '3273010101019002');
        $three = $this->registration($unit, $third, '3273010101019003');

        $this->assertEqualsCanonicalizing([$shared->id, $legacy->id], array_column($one->configuredTests(), 'id'));
        $this->assertSame([$shared->id], array_column($two->configuredTests(), 'id'));
        $this->assertSame([], $three->configuredTests());
    }

    public function test_invalid_foreign_program_scope_is_rejected(): void
    {
        [$unit, $first] = $this->programs();
        $foreign = Unit::create([
            'name' => 'Universitas Asing', 'code' => 'FOREIGN-T', 'institution_type' => 'university', 'is_active' => true,
        ]);
        $program = StudyProgram::create([
            'unit_id' => $foreign->id, 'code' => 'S1-BIO', 'name' => 'Biologi',
            'degree_level' => 'S1', 'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        AdmissionTest::create([
            'unit_id' => $unit->id, 'name' => 'Tes Salah Prodi',
            'study_program_ids' => [$first->id, $program->id],
        ]);
    }

    public function test_published_scope_is_pinned_after_master_changes(): void
    {
        [$unit, $first, $second, $third] = $this->programs();
        $test = AdmissionTest::create([
            'unit_id' => $unit->id, 'name' => 'Tes Terikat',
            'study_program_ids' => [$first->id, $second->id],
            'is_active' => true, 'is_required' => true,
        ]);

        $configuration = UnitConfiguration::create(array_merge(
            app(UnitConfigurationService::class)->defaults($unit),
            [
                'unit_id' => $unit->id, 'version' => 1, 'status' => 'published',
                'legacy' => false, 'published_at' => now(),
            ],
        ));

        $old = $this->registration($unit, $second, '3273010101019004', $configuration);
        $test->update(['study_program_ids' => [$third->id]]);

        $this->assertSame([$test->id], array_column($old->fresh()->configuredTests(), 'id'));
        $this->assertSame([$first->id, $second->id], $configuration->test_definitions[0]['study_program_ids']);
    }

    public function test_scope_cannot_change_if_booking_exists(): void
    {
        [$unit, $first, $second] = $this->programs();
        $test = AdmissionTest::create([
            'unit_id' => $unit->id, 'name' => 'Tes dengan Booking',
            'study_program_ids' => [$first->id],
            'is_active' => true,
        ]);
        $registration = $this->registration($unit, $first, '3273010101019005');
        TestBooking::create([
            'registration_id' => $registration->id, 'admission_test_id' => $test->id, 'revision' => 1,
        ]);

        $this->expectException(ValidationException::class);
        $test->update(['study_program_ids' => [$second->id]]);
    }

    /** @return array{Unit, StudyProgram, StudyProgram, StudyProgram} */
    private function programs(): array
    {
        $unit = Unit::create([
            'name' => 'Universitas A', 'code' => 'UNI-PROG-A',
            'institution_type' => 'university', 'is_active' => true,
        ]);
        $programs = [];
        foreach (['INF' => 'Informatika', 'FIS' => 'Fisika', 'MAT' => 'Matematika'] as $code => $name) {
            $programs[] = StudyProgram::create([
                'unit_id' => $unit->id, 'code' => 'S1-'.$code,
                'name' => $name, 'degree_level' => 'S1', 'is_active' => true,
            ]);
        }

        return [$unit, ...$programs];
    }

    private function registration(Unit $unit, StudyProgram $program, string $nik, ?UnitConfiguration $configuration = null): Registration
    {
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id, 'study_program_id' => $program->id,
            'academic_year' => '2026/2027', 'wave' => 'Gelombang 1',
            'status' => 'open', 'registration_fee' => 0,
        ]);

        return Registration::create([
            'user_id' => User::factory()->create()->id,
            'unit_id' => $unit->id, 'unit_configuration_id' => $configuration?->id,
            'registration_opening_id' => $opening->id,
            'nik' => $nik, 'full_name' => 'Peserta Tes Prodi',
            'gender' => 'L', 'birth_place' => 'Bandung',
            'birth_date' => '2008-01-01', 'home_address' => 'Bandung',
            'status' => 'submitted', 'current_stage' => 'data_validation',
        ]);
    }
}
