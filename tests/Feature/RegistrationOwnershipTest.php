<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_account_can_own_multiple_child_registrations(): void
    {
        $user = User::factory()->create();
        $unit = Unit::create(['name' => 'SD', 'code' => 'SD', 'is_active' => true]);

        foreach ([
            ['nik' => '1111111111111111', 'full_name' => 'Anak Satu'],
            ['nik' => '2222222222222222', 'full_name' => 'Anak Dua'],
        ] as $child) {
            Registration::create($child + [
                'user_id' => $user->id,
                'unit_id' => $unit->id,
                'registrant_type' => 'parent',
                'registrant_relationship' => 'father',
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2019-01-01',
                'home_address' => 'Bandung',
                'status' => 'submitted',
                'current_stage' => 'data_validation',
            ]);
        }

        $this->assertCount(2, $user->registrations);
    }

    public function test_applicant_cannot_view_another_users_registration_in_canonical_portal(): void
    {
        $owner = $this->applicant();
        $other = $this->applicant();
        $unit = Unit::create(['name' => 'SD', 'code' => 'SD', 'is_active' => true]);

        $registration = Registration::create([
            'user_id' => $owner->id,
            'unit_id' => $unit->id,
            'registrant_type' => 'self',
            'registrant_relationship' => 'self',
            'nik' => '3333333333333333',
            'full_name' => 'Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2019-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
        ]);

        $this->actingAs($other)
            ->get("/pendaftar/status/{$registration->id}")
            ->assertNotFound();
    }

    public function test_applicant_can_view_own_registration_in_canonical_portal(): void
    {
        $owner = $this->applicant();
        $unit = Unit::create(['name' => 'SD', 'code' => 'SD', 'is_active' => true]);

        $registration = Registration::create([
            'user_id' => $owner->id,
            'unit_id' => $unit->id,
            'registrant_type' => 'self',
            'registrant_relationship' => 'self',
            'nik' => '4444444444444444',
            'full_name' => 'Siswa Sendiri',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2019-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
        ]);

        $this->actingAs($owner)
            ->get("/pendaftar/status/{$registration->id}")
            ->assertOk();
    }

    private function applicant(): User
    {
        $role = Role::firstOrCreate([
            'name' => 'pendaftar',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
