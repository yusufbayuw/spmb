<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicAdmissionsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_opening_detail_is_shareable_and_archived_opening_is_hidden(): void
    {
        $unit = $this->schoolUnit([
            'public_contact_name' => 'Panitia SPMB SMA',
            'public_whatsapp' => '6281234567890',
        ]);
        $open = $this->opening($unit);

        $this->get(route('admissions.show', $open))
            ->assertOk()
            ->assertSee('SMA Taruna Bakti')
            ->assertSee('Daftar Sekarang')
            ->assertSee('Panitia SPMB SMA')
            ->assertSee('https://wa.me/6281234567890', false);

        $closed = $this->opening($unit, [
            'wave' => 'Gelombang Ditutup',
            'opened_at' => now()->subDays(10),
            'closed_at' => now()->subDay(),
        ]);

        $this->get(route('admissions.show', $closed))
            ->assertOk()
            ->assertSee('Ditutup')
            ->assertDontSee('Daftar Sekarang');

        $archived = $this->opening($unit, [
            'wave' => 'Gelombang Arsip',
            'status' => 'archived',
            'opened_at' => null,
            'closed_at' => null,
            'archived_at' => now(),
        ]);

        $this->get(route('admissions.show', $archived))->assertNotFound();
    }

    public function test_guest_apply_preserves_opening_intent_before_registration(): void
    {
        $opening = $this->opening($this->schoolUnit());

        $response = $this->get(route('admissions.apply', $opening));

        $response->assertRedirect('/pendaftar/register');
        $response->assertSessionHas('url.intended', function (string $url) use ($opening): bool {
            return str_contains($url, '/pendaftar/registrations/create')
                && str_contains($url, 'opening='.$opening->uuid);
        });
    }

    public function test_verified_applicant_can_choose_existing_registration_or_add_another_participant(): void
    {
        $unit = $this->schoolUnit();
        $opening = $this->opening($unit);
        $applicant = $this->userWithRole('pendaftar');
        $existing = $this->registration($applicant, $opening, '3273010101010001', 'Alya Putri');

        $this->actingAs($applicant)
            ->get(route('admissions.apply', $opening))
            ->assertOk()
            ->assertSee('Alya Putri')
            ->assertSee('Lanjutkan Pendaftaran')
            ->assertSee('Daftarkan Peserta Lain');

        $response = $this->actingAs($applicant)
            ->get(route('admissions.apply', ['registrationOpening' => $opening, 'new' => 1]));

        $this->assertStringContainsString('/pendaftar/registrations/create', (string) $response->headers->get('Location'));
        $this->assertStringContainsString('opening='.$opening->uuid, (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('registrations', ['id' => $existing->id]);
    }

    public function test_unverified_applicant_keeps_intent_and_is_sent_to_verification_flow(): void
    {
        $opening = $this->opening($this->schoolUnit());
        $applicant = $this->userWithRole('pendaftar', ['email_verified_at' => null]);

        $response = $this->actingAs($applicant)->get(route('admissions.apply', $opening));

        $response->assertRedirect('/pendaftar');
        $response->assertSessionHas('url.intended');
    }

    public function test_same_account_can_register_multiple_children_but_same_nik_cannot_repeat_in_one_opening(): void
    {
        $unit = $this->schoolUnit();
        $opening = $this->opening($unit);
        $applicant = $this->userWithRole('pendaftar');

        $this->registration($applicant, $opening, '3273010101010001', 'Alya Putri');
        $this->registration($applicant, $opening, '3273010101010002', 'Raka Putra');

        $this->assertDatabaseCount('registrations', 2);

        $this->expectException(QueryException::class);
        $this->registration($applicant, $opening, '3273010101010001', 'Alya Duplikat');
    }

    private function schoolUnit(array $attributes = []): Unit
    {
        return Unit::create($attributes + [
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
    }

    private function opening(Unit $unit, array $attributes = []): RegistrationOpening
    {
        return RegistrationOpening::create($attributes + [
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 300000,
            'status' => 'open',
            'opened_at' => now()->subDay(),
            'closed_at' => now()->addDays(10),
        ]);
    }

    private function registration(User $user, RegistrationOpening $opening, string $nik, string $name): Registration
    {
        return Registration::create([
            'user_id' => $user->id,
            'unit_id' => $opening->unit_id,
            'registration_opening_id' => $opening->id,
            'nik' => $nik,
            'full_name' => $name,
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2012-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create($attributes + [
            'role' => 'user',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
