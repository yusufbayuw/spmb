<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationOpeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_listing_shows_open_and_closed_but_hides_draft_and_archived_openings(): void
    {
        $unit = Unit::create(['name' => 'SMA Taruna Bakti', 'code' => 'SMA', 'is_active' => true]);

        foreach (['draft', 'open', 'closed', 'archived'] as $status) {
            RegistrationOpening::create([
                'unit_id' => $unit->id,
                'academic_year' => '2026/2027',
                'wave' => ucfirst($status),
                'status' => $status,
            ]);
        }

        $statuses = RegistrationOpening::query()
            ->visibleToApplicants()
            ->pluck('status')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['closed', 'open'], $statuses);
    }

    public function test_registration_uses_unit_from_selected_opening(): void
    {
        $unit = Unit::create(['name' => 'SMA Taruna Bakti', 'code' => 'SMA', 'is_active' => true]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
        ]);
        $pathway = RegistrationPathway::create([
            'unit_id' => $unit->id,
            'name' => 'Prestasi',
            'is_active' => true,
        ]);

        $registration = Registration::create([
            'user_id' => User::factory()->create()->id,
            'unit_id' => $opening->unit_id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => '3273010101010001',
            'full_name' => 'Calon Siswa',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);

        $this->assertSame($unit->id, $registration->opening->unit_id);
        $this->assertSame('Prestasi', $registration->pathway->name);
    }

    public function test_opening_automatically_changes_availability_from_its_schedule(): void
    {
        $this->travelTo('2026-09-04 08:00:00');
        $unit = Unit::create(['name' => 'SMA Taruna Bakti', 'code' => 'SMA', 'is_active' => true]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang Terjadwal',
            'status' => 'draft',
            'opened_at' => '2026-09-04 09:00:00',
            'closed_at' => '2026-09-04 17:00:00',
        ]);

        $this->assertSame('scheduled', $opening->operationalStatus());
        $this->assertFalse($opening->isOpen());
        $this->assertSame(0, RegistrationOpening::query()->currentlyOpen()->count());

        $this->travelTo('2026-09-04 12:00:00');
        $this->assertSame('open', $opening->operationalStatus());
        $this->assertTrue($opening->isOpen());
        $this->assertSame(1, RegistrationOpening::query()->currentlyOpen()->count());

        $this->travelTo('2026-09-04 17:00:00');
        $this->assertSame('closed', $opening->operationalStatus());
        $this->assertFalse($opening->isOpen());
        $this->assertSame(0, RegistrationOpening::query()->currentlyOpen()->count());
    }

    public function test_closed_opening_cannot_open_applicant_form_but_open_opening_can(): void
    {
        $unit = Unit::create(['name' => 'SMA Taruna Bakti', 'code' => 'SMA', 'is_active' => true]);
        $applicant = $this->userWithRole('pendaftar');

        $open = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
        ]);

        $closed = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 2',
            'status' => 'closed',
        ]);

        $this->actingAs($applicant)
            ->get("/pendaftar/registrations/create?opening={$open->id}")
            ->assertOk();

        $this->actingAs($applicant)
            ->get("/pendaftar/registrations/create?opening={$closed->id}")
            ->assertForbidden();
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
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
