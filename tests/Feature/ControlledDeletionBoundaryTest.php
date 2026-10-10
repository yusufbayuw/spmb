<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\ControlledDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ControlledDeletionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_unit_admin_cannot_delete_opening(): void
    {
        $owner = Unit::create(['name' => 'Owner', 'code' => 'OWNERD', 'is_active' => true, 'allow_admin_unit_opening_deletion' => true]);
        $other = Unit::create(['name' => 'Other', 'code' => 'OTHERD', 'is_active' => true, 'allow_admin_unit_opening_deletion' => true]);
        $opening = $this->opening($owner);
        $staff = $this->staff($other);

        try {
            app(ControlledDeletionService::class)->deleteOpening($opening, $staff, 'Uji batas unit', 'HAPUS');
            $this->fail('Foreign unit must not delete opening.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseHas('registration_openings', ['id' => $opening->id]);
    }

    public function test_opening_with_registration_cannot_be_deleted(): void
    {
        $unit = Unit::create(['name' => 'Protected', 'code' => 'PROTD', 'is_active' => true, 'allow_admin_unit_opening_deletion' => true]);
        $opening = $this->opening($unit);
        Registration::create([
            'user_id' => User::factory()->create()->id,
            'unit_id' => $unit->id, 'registration_opening_id' => $opening->id,
            'nik' => '3273010101010009', 'full_name' => 'Test Child', 'gender' => 'L',
            'birth_place' => 'Bandung', 'birth_date' => '2017-01-01',
            'home_address' => 'Bandung', 'status' => 'submitted',
        ]);

        try {
            app(ControlledDeletionService::class)->deleteOpening($opening, $this->staff($unit), 'Uji relasi', 'HAPUS');
            $this->fail('Populated opening must not be deleted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delete', $e->errors());
        }
        $this->assertDatabaseHas('registration_openings', ['id' => $opening->id]);
    }

    private function opening(Unit $unit): RegistrationOpening
    {
        return RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2026/2027',
            'wave' => 'Tes Hapus', 'registration_fee' => 0, 'status' => 'draft',
        ]);
    }

    private function staff(Unit $unit): User
    {
        Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        $staff = User::factory()->create(['unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true]);
        $staff->assignRole('admin_unit');

        return $staff;
    }
}
