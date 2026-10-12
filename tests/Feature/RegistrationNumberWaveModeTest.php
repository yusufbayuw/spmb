<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\RegistrationNumberService;
use App\Services\UnitConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationNumberWaveModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_mode_keeps_original_per_unit_counter_across_waves_and_years(): void
    {
        [$unit, $configuration] = $this->setupUnit();
        $first = $this->opening($unit, '2026/2027', 'Gelombang 1');
        $second = $this->opening($unit, '2026/2027', 'Gelombang 2');
        $third = $this->opening($unit, '2027/2028', 'Gelombang 1');
        $numbers = app(RegistrationNumberService::class);

        $this->assertSame(RegistrationNumberService::MODE_UNIT, $numbers->modeFor($first));
        $this->assertSame('SMP-001', $numbers->assign($this->registration($unit, $configuration, $first, '3273010101010001')));
        $this->assertSame('SMP-002', $numbers->assign($this->registration($unit, $configuration, $second, '3273010101010002')));
        $this->assertSame('SMP-003', $numbers->assign($this->registration($unit, $configuration, $third, '3273010101010003')));
        $this->assertDatabaseHas('registration_number_sequences', ['unit_id' => $unit->id, 'last_number' => 3]);
        $this->assertDatabaseCount('registration_number_wave_sequences', 0);
    }

    public function test_wave_mode_resets_each_wave_and_year_without_touching_existing_unit_counter(): void
    {
        [$unit, $configuration] = $this->setupUnit();
        $old = $this->opening($unit, '2026/2027', 'Gelombang Lama');
        $first = $this->opening($unit, '2026/2027', 'Gelombang 1');
        $second = $this->opening($unit, '2026/2027', 'Gelombang 2');
        $nextYear = $this->opening($unit, '2027/2028', 'Gelombang 1');
        $numbers = app(RegistrationNumberService::class);

        $this->assertSame('SMP-001', $numbers->assign($this->registration($unit, $configuration, $old, '3273010101010001')));
        $numbers->changeMode($first, RegistrationNumberService::MODE_WAVE);
        $numbers->changeMode($second, RegistrationNumberService::MODE_WAVE);
        $numbers->changeMode($nextYear, RegistrationNumberService::MODE_WAVE);

        $this->assertSame('SMP-20262027-G1-001', $numbers->assign($this->registration($unit, $configuration, $first, '3273010101010002')));
        $this->assertSame('SMP-20262027-G1-002', $numbers->assign($this->registration($unit, $configuration, $first, '3273010101010003')));
        $this->assertSame('SMP-20262027-G2-001', $numbers->assign($this->registration($unit, $configuration, $second, '3273010101010004')));
        $this->assertSame('SMP-20272028-G1-001', $numbers->assign($this->registration($unit, $configuration, $nextYear, '3273010101010005')));
        $this->assertSame('SMP-002', $numbers->assign($this->registration($unit, $configuration, $old, '3273010101010006')));
        $this->assertDatabaseHas('registration_number_sequences', ['unit_id' => $unit->id, 'last_number' => 2]);
        $this->assertDatabaseHas('registration_number_wave_sequences', ['unit_id' => $unit->id, 'academic_year' => '2026/2027', 'wave' => 'Gelombang 1', 'last_number' => 2]);
    }

    public function test_multiple_programs_in_the_same_wave_share_one_mode_and_sequence(): void
    {
        [$unit, $configuration] = $this->setupUnit('university');
        $programA = StudyProgram::create(['unit_id' => $unit->id, 'code' => 'IF', 'name' => 'Informatika', 'degree_level' => 'S1', 'is_active' => true]);
        $programB = StudyProgram::create(['unit_id' => $unit->id, 'code' => 'SI', 'name' => 'Sistem Informasi', 'degree_level' => 'S1', 'is_active' => true]);
        $a = $this->opening($unit, '2026/2027', 'Gelombang 1', $programA->id);
        $b = $this->opening($unit, '2026/2027', 'Gelombang 1', $programB->id);
        $numbers = app(RegistrationNumberService::class);
        $numbers->changeMode($a, RegistrationNumberService::MODE_WAVE);

        $this->assertSame(RegistrationNumberService::MODE_WAVE, $numbers->modeFor($b));
        $this->assertSame('PT-20262027-G1-001', $numbers->assign($this->registration($unit, $configuration, $a, '3273010101010011')));
        $this->assertSame('PT-20262027-G1-002', $numbers->assign($this->registration($unit, $configuration, $b, '3273010101010012')));
        $this->assertDatabaseCount('registration_number_wave_sequences', 1);
    }

    public function test_a_started_wave_can_switch_before_any_number_is_issued(): void
    {
        [$unit, $configuration] = $this->setupUnit();
        $opening = $this->opening($unit, '2026/2027', 'Gelombang 1');
        $numbers = app(RegistrationNumberService::class);
        $pending = $this->registration($unit, $configuration, $opening, '3273010101010001');

        $numbers->changeMode($opening, RegistrationNumberService::MODE_WAVE);
        $this->assertSame(RegistrationNumberService::MODE_WAVE, $numbers->modeFor($opening));
        $numbers->changeMode($opening, RegistrationNumberService::MODE_UNIT);
        $this->assertSame(RegistrationNumberService::MODE_UNIT, $numbers->modeFor($opening));
        $this->assertDatabaseCount('registration_number_wave_sequences', 0);
        $this->assertSame('SMP-001', $numbers->assign($pending));
    }

    public function test_issued_unit_number_blocks_switching_that_wave_but_not_another_wave(): void
    {
        [$unit, $configuration] = $this->setupUnit();
        $first = $this->opening($unit, '2026/2027', 'Gelombang 1');
        $second = $this->opening($unit, '2026/2027', 'Gelombang 2');
        $numbers = app(RegistrationNumberService::class);
        $issued = $this->registration($unit, $configuration, $first, '3273010101010001');
        $this->assertSame('SMP-001', $numbers->assign($issued));

        try {
            $numbers->changeMode($first, RegistrationNumberService::MODE_WAVE);
            $this->fail('Changing a numbered wave must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('mode', $exception->errors());
        }

        $this->assertSame('SMP-001', $issued->fresh()->registration_number);
        $numbers->changeMode($second, RegistrationNumberService::MODE_WAVE);
        $this->assertSame(RegistrationNumberService::MODE_WAVE, $numbers->modeFor($second));
    }

    public function test_issued_wave_number_locks_mode_and_period_but_preserves_number(): void
    {
        [$unit, $configuration] = $this->setupUnit();
        $opening = $this->opening($unit, '2026/2027', 'Gelombang 1');
        $numbers = app(RegistrationNumberService::class);
        $numbers->changeMode($opening, RegistrationNumberService::MODE_WAVE);
        $registration = $this->registration($unit, $configuration, $opening, '3273010101010001');
        $number = $numbers->assign($registration);

        $this->expectException(ValidationException::class);
        try {
            $numbers->changeMode($opening, RegistrationNumberService::MODE_UNIT);
        } finally {
            $this->assertSame($number, $registration->fresh()->registration_number);
            $this->assertSame(RegistrationNumberService::MODE_WAVE, $numbers->modeFor($opening));
        }
    }

    public function test_wave_mode_blocks_moving_numbered_opening_to_another_group(): void
    {
        [$unit, $configuration] = $this->setupUnit();
        $opening = $this->opening($unit, '2026/2027', 'Gelombang 1');
        $numbers = app(RegistrationNumberService::class);
        $numbers->changeMode($opening, RegistrationNumberService::MODE_WAVE);
        $numbers->assign($this->registration($unit, $configuration, $opening, '3273010101010001'));

        $this->expectException(ValidationException::class);
        $opening->update(['wave' => 'Gelombang Baru']);
    }

    /** @return array{Unit, UnitConfiguration} */
    private function setupUnit(string $type = 'school'): array
    {
        $unit = Unit::create([
            'name' => $type === 'university' ? 'Perguruan Tinggi Test' : 'SMP Test',
            'code' => $type === 'university' ? 'PT' : 'SMP',
            'institution_type' => $type,
            'is_active' => true,
        ]);
        $configuration = UnitConfiguration::create(array_merge(
            app(UnitConfigurationService::class)->defaults($unit),
            [
                'unit_id' => $unit->id,
                'version' => 1,
                'status' => 'published',
                'payment_enabled' => false,
                'registration_number_prefix' => $unit->code,
                'registration_number_digits' => 3,
                'published_at' => now(),
            ],
        ));

        return [$unit, $configuration];
    }

    private function opening(Unit $unit, string $year, string $wave, ?int $programId = null): RegistrationOpening
    {
        return RegistrationOpening::create([
            'unit_id' => $unit->id,
            'study_program_id' => $programId,
            'academic_year' => $year,
            'wave' => $wave,
            'status' => 'open',
            'registration_fee' => 0,
        ]);
    }

    private function registration(Unit $unit, UnitConfiguration $configuration, RegistrationOpening $opening, string $nik): Registration
    {
        return Registration::create([
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'unit_id' => $unit->id,
            'unit_configuration_id' => $configuration->id,
            'registration_opening_id' => $opening->id,
            'full_name' => 'Peserta '.$nik,
            'nik' => $nik,
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2020-01-01',
            'home_address' => 'Bandung',
            'data_validation_status' => 'valid',
            'current_stage' => 'data_validation',
        ]);
    }
}
