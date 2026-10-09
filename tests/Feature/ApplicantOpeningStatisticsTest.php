<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\RegistrationOpeningResource\Pages\EditRegistrationOpening;
use App\Filament\Applicant\Pages\RegistrationOpenings;
use App\Filament\Applicant\Pages\RegistrationStatus;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicantOpeningStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_are_opt_in_and_count_only_active_registrations_in_the_selected_opening(): void
    {
        $unit = $this->unit();
        $opening = $this->opening($unit, 'Gelombang 1');
        $other = $this->opening($unit, 'Gelombang 2');

        $this->registration($opening, '3273010101010041', 'valid');
        $this->registration($opening, '3273010101010042', 'pending');
        $this->registration($opening, '3273010101010043', 'valid', 'cancelled');
        $this->registration($other, '3273010101010044', 'valid');

        $counts = RegistrationOpening::query()->withApplicantStatistics()->whereKey($opening)->firstOrFail();

        $this->assertFalse($counts->show_total_applicants);
        $this->assertFalse($counts->show_verified_applicants);
        $this->assertSame(2, $counts->applicant_total_count);
        $this->assertSame(1, $counts->applicant_verified_count);

        $opening->update(['show_total_applicants' => true, 'show_verified_applicants' => true]);
        $counts = RegistrationOpening::query()->withApplicantStatistics()->whereKey($opening)->firstOrFail();
        $this->assertTrue($counts->show_total_applicants);
        $this->assertTrue($counts->show_verified_applicants);
    }

    public function test_applicant_listing_shows_only_enabled_statistics_for_each_opening(): void
    {
        $unit = $this->unit();
        $opening = $this->opening($unit, 'Gelombang Statistik');
        $this->registration($opening, '3273010101010045', 'valid');

        $applicant = $this->applicant();
        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(RegistrationOpenings::class)
            ->assertDontSee('Total Pendaftar')
            ->assertDontSee('Data Terverifikasi');

        $opening->update(['show_total_applicants' => true]);

        Livewire::test(RegistrationOpenings::class)
            ->assertSee('Total Pendaftar')
            ->assertDontSee('Data Terverifikasi');

        $opening->update(['show_total_applicants' => false, 'show_verified_applicants' => true]);

        Livewire::test(RegistrationOpenings::class)
            ->assertDontSee('Total Pendaftar')
            ->assertSee('Data Terverifikasi');
    }

    public function test_status_page_respects_its_opening_settings_and_ownership(): void
    {
        $unit = $this->unit();
        $opening = $this->opening($unit, 'Gelombang Detail');
        $opening->update(['show_total_applicants' => true, 'show_verified_applicants' => true]);
        $applicant = $this->applicant();
        $registration = $this->registration($opening, '3273010101010046', 'valid', 'active', $applicant);

        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(RegistrationStatus::class, ['registration' => $registration->uuid])
            ->assertSee('Statistik Gelombang Detail')
            ->assertSee('Total Pendaftar')
            ->assertSee('Data Terverifikasi');

        $this->actingAs($this->applicant());
        Livewire::test(RegistrationStatus::class, ['registration' => $registration->uuid])
            ->assertNotFound();
    }

    public function test_admin_unit_can_toggle_statistics_without_changing_registration_workflow(): void
    {
        $this->seed(ShieldSeeder::class);
        $unit = $this->unit();
        $opening = $this->opening($unit, 'Gelombang Admin');
        $opening->update([
            'opened_at' => now()->subDay(),
            'closed_at' => now()->addDays(5),
        ]);
        $staff = User::factory()->create(['role' => 'admin_unit', 'unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditRegistrationOpening::class, ['record' => $opening->getRouteKey()])
            ->fillForm(['show_total_applicants' => true, 'show_verified_applicants' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($opening->fresh()->show_total_applicants);
        $this->assertTrue($opening->fresh()->show_verified_applicants);
        $this->assertSame('open', $opening->fresh()->status);
    }

    private function unit(): Unit
    {
        return Unit::create(['name' => 'Unit Statistik', 'code' => 'STAT-UNIT', 'is_active' => true]);
    }

    private function opening(Unit $unit, string $wave): RegistrationOpening
    {
        return RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => $wave,
            'status' => 'open',
            'registration_fee' => 0,
        ]);
    }

    private function applicant(): User
    {
        Role::firstOrCreate(['name' => 'pendaftar', 'guard_name' => 'web']);

        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $user->assignRole('pendaftar');

        return $user;
    }

    private function registration(
        RegistrationOpening $opening,
        string $nik,
        string $validationStatus,
        string $lifecycle = 'active',
        ?User $applicant = null,
    ): Registration {
        return Registration::create([
            'user_id' => ($applicant ?? User::factory()->create())->id,
            'unit_id' => $opening->unit_id,
            'registration_opening_id' => $opening->id,
            'nik' => $nik,
            'full_name' => 'Calon Siswa Statistik',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'lifecycle_status' => $lifecycle,
            'data_validation_status' => $validationStatus,
        ]);
    }
}
