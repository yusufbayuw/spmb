<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistrationPathwayTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_unarchived_pathways_are_available_for_the_selected_unit(): void
    {
        $unit = $this->unit('SMA');
        $otherUnit = $this->unit('SMP');
        $available = $this->pathway($unit, 'Reguler');
        $this->pathway($unit, 'Prestasi', false);
        $archived = $this->pathway($unit, 'Afirmasi');
        $archived->archive();
        $this->pathway($otherUnit, 'Reguler');

        $pathways = RegistrationPathway::query()
            ->availableForUnit($unit->id)
            ->pluck('name')
            ->all();

        $this->assertSame([$available->name], $pathways);
    }

    public function test_registration_rejects_a_pathway_from_another_unit(): void
    {
        $unit = $this->unit('SMA');
        $otherUnit = $this->unit('SMP');
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
        ]);
        $otherPathway = $this->pathway($otherUnit, 'Reguler');

        $this->expectException(ValidationException::class);

        Registration::create([
            'user_id' => User::factory()->create()->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $otherPathway->id,
            'registrant_type' => 'self',
            'nik' => '3273010101010099',
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
        ]);
    }

    public function test_tu_can_only_manage_pathways_from_their_own_unit(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = $this->unit('SMA');
        $otherUnit = $this->unit('SMP');
        $ownPathway = $this->pathway($unit, 'Reguler');
        $otherPathway = $this->pathway($otherUnit, 'Prestasi');
        $staff = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('tu');

        $this->assertTrue($staff->can('update', $ownPathway));
        $this->assertFalse($staff->can('update', $otherPathway));
    }

    private function unit(string $code): Unit
    {
        return Unit::create([
            'name' => 'Unit '.$code,
            'code' => $code,
            'institution_type' => 'school',
            'is_active' => true,
        ]);
    }

    private function pathway(Unit $unit, string $name, bool $isActive = true): RegistrationPathway
    {
        return RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => $name,
            'is_active' => $isActive,
        ]);
    }
}
