<?php

namespace Tests\Feature;

use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAdmissionsHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_prioritizes_open_and_upcoming_school_admissions(): void
    {
        $unit = $this->schoolUnit();

        $open = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang Aktif',
            'registration_fee' => 300000,
            'status' => 'open',
            'opened_at' => now()->subDay(),
            'closed_at' => now()->addDays(10),
        ]);

        RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang Mendatang',
            'registration_fee' => 0,
            'status' => 'draft',
            'opened_at' => now()->addDays(20),
            'closed_at' => now()->addDays(40),
        ]);

        RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2025/2026',
            'wave' => 'Gelombang Arsip',
            'registration_fee' => 100000,
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        $response = $this->get('/?kategori=sekolah');

        $response->assertOk();
        $response->assertSee('Pendaftaran Taruna Bakti');
        $response->assertSee('Pilih jenjang dan pembukaan pendaftaran');
        $response->assertSee('Gelombang Aktif');
        $response->assertSee('Rp 300.000');
        $response->assertSee('Gelombang Mendatang');
        $response->assertSee('Akan Dibuka');
        $response->assertDontSee('Gelombang Arsip');
        $response->assertDontSee('Portal Resmi Penerimaan');
        $response->assertSee(route('admissions.show', $open), false);
        $response->assertSee(route('admissions.apply', $open), false);
    }

    public function test_homepage_has_useful_empty_state_when_only_upcoming_opening_exists(): void
    {
        $unit = $this->schoolUnit();

        RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'draft',
            'opened_at' => now()->addWeek(),
            'closed_at' => now()->addMonth(),
        ]);

        $this->get('/?kategori=sekolah')
            ->assertOk()
            ->assertSee('Belum ada pendaftaran yang sedang dibuka')
            ->assertSee('Jadwal penerimaan berikutnya')
            ->assertSee('Gratis');
    }

    public function test_university_filter_shows_program_level_opening(): void
    {
        $university = Unit::create([
            'name' => 'Taruna Bakti University',
            'code' => 'TBU',
            'institution_type' => 'university',
            'is_active' => true,
        ]);

        $program = StudyProgram::create([
            'unit_id' => $university->id,
            'code' => 'IF',
            'name' => 'Teknik Informatika',
            'degree_level' => 'S1',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        RegistrationOpening::create([
            'unit_id' => $university->id,
            'study_program_id' => $program->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 250000,
            'status' => 'open',
            'opened_at' => now()->subDay(),
            'closed_at' => now()->addDays(10),
        ]);

        $this->get('/?kategori=universitas&jenjang=S1')
            ->assertOk()
            ->assertSee('S1 Teknik Informatika')
            ->assertSee('Taruna Bakti University')
            ->assertSee('Rp 250.000');
    }

    private function schoolUnit(): Unit
    {
        return Unit::create([
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
    }
}
