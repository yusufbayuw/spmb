<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\RegistrationNumberService;
use App\Services\UnitConfigurationService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_prefix_and_digits_generate_sequential_registration_numbers(): void
    {
        $unit = Unit::create([
            'name' => 'SMP Test',
            'code' => 'SMP',
            'is_active' => true,
        ]);

        $configuration = UnitConfiguration::create(array_merge(
            app(UnitConfigurationService::class)->defaults($unit),
            [
                'unit_id' => $unit->id,
                'version' => 1,
                'status' => 'published',
                'payment_enabled' => false,
                'registration_number_prefix' => 'SMP',
                'registration_number_digits' => 3,
                'published_at' => now(),
            ],
        ));

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);

        $first = $this->registration($unit, $configuration, $opening, '3273010101010001');
        $second = $this->registration($unit, $configuration, $opening, '3273010101010002');

        $firstNumber = DB::transaction(
            fn (): string => app(RegistrationNumberService::class)->assign(
                Registration::query()->lockForUpdate()->findOrFail($first->id),
            ),
        );
        $secondNumber = DB::transaction(
            fn (): string => app(RegistrationNumberService::class)->assign(
                Registration::query()->lockForUpdate()->findOrFail($second->id),
            ),
        );

        $this->assertSame('SMP-001', $firstNumber);
        $this->assertSame('SMP-002', $secondNumber);
        $this->assertSame('KARTU-SMP-001', $first->fresh()->generateApplicantCardNumber());
    }

    public function test_blank_prefix_keeps_legacy_format_and_respects_configured_digits(): void
    {
        $unit = Unit::create([
            'name' => 'SMA Test',
            'code' => 'SMA',
            'is_active' => true,
        ]);

        $configuration = UnitConfiguration::create(array_merge(
            app(UnitConfigurationService::class)->defaults($unit),
            [
                'unit_id' => $unit->id,
                'version' => 1,
                'status' => 'published',
                'payment_enabled' => false,
                'registration_number_prefix' => null,
                'registration_number_digits' => 5,
                'published_at' => now(),
            ],
        ));

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
        ]);

        $registration = $this->registration($unit, $configuration, $opening, '3273010101010003');

        $number = DB::transaction(
            fn (): string => app(RegistrationNumberService::class)->assign(
                Registration::query()->lockForUpdate()->findOrFail($registration->id),
            ),
        );

        $this->assertSame('REG-SMA-20262027-00001', $number);
        $this->assertSame('KARTU-SMA-20262027-00001', $registration->fresh()->generateApplicantCardNumber());
    }

    public function test_admin_configuration_normalizes_prefix_and_requires_at_least_three_digits(): void
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'SMP Test',
            'code' => 'SMP',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('admin_unit');

        $service = app(UnitConfigurationService::class);
        $draft = $service->draft($unit, $staff);
        $data = $draft->toArray();
        $data['registration_number_prefix'] = 'smp-reg';
        $data['registration_number_digits'] = 3;

        $published = $service->save($draft, $staff, $data, true);

        $this->assertSame('SMP-REG', $published->registration_number_prefix);
        $this->assertSame(3, $published->registration_number_digits);

        $nextDraft = $service->draft($unit, $staff);
        $invalid = $nextDraft->toArray();
        $invalid['registration_number_digits'] = 2;

        $this->expectException(ValidationException::class);

        $service->save($nextDraft, $staff, $invalid);
    }

    private function registration(
        Unit $unit,
        UnitConfiguration $configuration,
        RegistrationOpening $opening,
        string $nik,
    ): Registration {
        $user = User::factory()->create(['is_active' => true]);

        return Registration::create([
            'user_id' => $user->id,
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
