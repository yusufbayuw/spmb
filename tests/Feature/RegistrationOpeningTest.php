<?php

namespace Tests\Feature;

use App\Filament\Applicant\Pages\RegistrationOpenings;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->get("/pendaftar/registrations/create?opening={$open->uuid}")
            ->assertOk();

        $this->actingAs($applicant)
            ->get("/pendaftar/registrations/create?opening={$closed->uuid}")
            ->assertForbidden();
    }

    public function test_applicant_can_search_filter_and_reset_registration_openings(): void
    {
        $this->travelTo('2026-09-04 08:00:00');
        $school = Unit::create(['name' => 'SMA Taruna Bakti', 'code' => 'SMA', 'is_active' => true]);
        $university = Unit::create(['name' => 'Taruna Bakti University', 'code' => 'TBU', 'institution_type' => 'university', 'is_active' => true]);
        $program = StudyProgram::create(['unit_id' => $university->id, 'code' => 'S1-IF', 'name' => 'Informatika', 'degree_level' => 'S1', 'is_active' => true]);

        RegistrationOpening::create([
            'unit_id' => $school->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang Terbuka',
            'status' => 'open',
        ]);
        RegistrationOpening::create([
            'unit_id' => $school->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang Mendatang',
            'status' => 'draft',
            'opened_at' => '2026-09-05 08:00:00',
            'closed_at' => '2026-09-06 16:00:00',
        ]);
        RegistrationOpening::create([
            'unit_id' => $university->id,
            'study_program_id' => $program->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang Ditutup',
            'status' => 'closed',
        ]);

        $this->actingAs($this->userWithRole('pendaftar'));
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        $page = Livewire::test(RegistrationOpenings::class)
            ->assertSet('availability', 'open')
            ->assertSee('Gelombang Terbuka')
            ->assertDontSee('Gelombang Mendatang')
            ->assertDontSee('Gelombang Ditutup');

        $page->set('availability', 'scheduled')
            ->assertSee('Gelombang Mendatang')
            ->assertDontSee('Gelombang Terbuka')
            ->set('search', 'Mendatang')
            ->assertSee('Gelombang Mendatang')
            ->set('search', '')
            ->set('availability', 'all')
            ->call('selectEducationLevel', 'S1')
            ->assertSet('educationLevelCode', 'S1')
            ->assertSee('Gelombang Ditutup')
            ->assertDontSee('Gelombang Terbuka')
            ->call('clearFilters')
            ->assertSet('educationLevelCode', null)
            ->assertSet('availability', 'open')
            ->assertSee('Gelombang Terbuka')
            ->assertDontSee('Gelombang Mendatang')
            ->assertDontSee('Gelombang Ditutup');
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
