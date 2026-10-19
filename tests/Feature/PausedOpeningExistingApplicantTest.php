<?php

namespace Tests\Feature;

use App\Filament\Applicant\Pages\RegistrationStatus;
use App\Filament\Applicant\Resources\RegistrationResource;
use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Services\UnitConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PausedOpeningExistingApplicantTest extends TestCase
{
    use RefreshDatabase;

    public function test_paused_old_links_redirect_an_existing_applicant_to_their_registration(): void
    {
        [$opening, $applicant] = $this->openingAndApplicant();
        $registration = $this->registration($opening, $applicant);
        $opening->pause();

        $target = RegistrationStatus::getUrl(['registration' => $registration->uuid], panel: 'pendaftar');

        $this->actingAs($applicant)
            ->get(route('admissions.apply', $opening))
            ->assertRedirect($target);

        $this->actingAs($applicant)
            ->get('/pendaftar/registrations/create?opening='.$opening->uuid)
            ->assertRedirect($target);

        $this->actingAs($applicant)->get('/pendaftar')->assertOk()->assertSee($registration->full_name);
        $this->actingAs($applicant)->get($target)->assertOk()->assertSee($registration->full_name);
        $this->actingAs($applicant)
            ->get(RegistrationResource::getUrl('index', panel: 'pendaftar'))
            ->assertOk()->assertSee($registration->full_name);
        $this->actingAs($applicant)
            ->get(RegistrationResource::getUrl('edit', ['record' => $registration], panel: 'pendaftar'))
            ->assertOk();

        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_paused_links_remain_hidden_from_guests_and_applicants_without_an_owned_record(): void
    {
        [$opening, $owner] = $this->openingAndApplicant();
        $this->registration($opening, $owner);
        $opening->pause();

        $this->get(route('admissions.apply', $opening))->assertNotFound();

        $stranger = $this->applicant();
        $this->actingAs($stranger)->get(route('admissions.apply', $opening))->assertNotFound();
        $this->actingAs($stranger)
            ->get('/pendaftar/registrations/create?opening='.$opening->uuid)
            ->assertNotFound();

        $this->assertDatabaseCount('registrations', 1);
    }

    public function test_paused_links_show_existing_registration_list_for_multiple_children_and_never_open_new_form(): void
    {
        [$opening, $applicant] = $this->openingAndApplicant();
        $this->registration($opening, $applicant, '3273010101010001', 'Anak Pertama');
        $this->registration($opening, $applicant, '3273010101010002', 'Anak Kedua');
        $opening->pause();

        $target = RegistrationResource::getUrl('index', panel: 'pendaftar');

        $this->actingAs($applicant)
            ->get(route('admissions.apply', ['registrationOpening' => $opening, 'new' => 1]))
            ->assertRedirect($target);
        $this->actingAs($applicant)
            ->get('/pendaftar/registrations/create?opening='.$opening->uuid)
            ->assertRedirect($target);
        $this->assertDatabaseCount('registrations', 2);
    }

    public function test_paused_opening_does_not_interrupt_existing_payment_and_document_steps(): void
    {
        [$opening, $applicant] = $this->openingAndApplicant();
        $registration = $this->registration($opening, $applicant);
        $opening->pause();

        $registration->update(['current_stage' => 'payment', 'data_validation_status' => 'valid']);
        Payment::create(['registration_id' => $registration->id, 'amount' => 0, 'status' => 'pending']);

        $this->actingAs($applicant)
            ->get('/pendaftar/pembayaran/'.$registration->uuid)
            ->assertOk();

        $registration->update(['current_stage' => 'documents']);

        $this->actingAs($applicant)
            ->get('/pendaftar/dokumen/'.$registration->uuid)
            ->assertOk();
    }

    public function test_submission_rechecks_paused_state_inside_the_creation_transaction(): void
    {
        [$opening, $applicant] = $this->openingAndApplicant();
        $opening->pause();
        $this->actingAs($applicant);

        // Invoke the creation handler directly to exercise the final server-side
        // guard even when a form was mounted before the administrator paused it.
        $handler = new ReflectionMethod(CreateRegistration::class, 'handleRecordCreation');

        try {
            $handler->invoke(app(CreateRegistration::class), [
                'unit_id' => $opening->unit_id,
                'registration_opening_id' => $opening->id,
            ]);
            $this->fail('A paused opening must not accept a new registration.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration_opening_uuid', $exception->errors());
        }

        $this->assertDatabaseCount('registrations', 0);
    }

    /** @return array{RegistrationOpening, User} */
    private function openingAndApplicant(): array
    {
        $unit = Unit::create([
            'name' => 'SMA Contoh',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        app(UnitConfigurationService::class)->initialize($unit);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'status' => 'open',
            'registration_fee' => 0,
            'opened_at' => now()->subDay(),
            'closed_at' => now()->addWeek(),
        ]);

        return [$opening, $this->applicant()];
    }

    private function applicant(): User
    {
        $role = Role::firstOrCreate(['name' => 'pendaftar', 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function registration(
        RegistrationOpening $opening,
        User $applicant,
        string $nik = '3273010101010001',
        string $name = 'Calon Siswa'
    ): Registration {
        return Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $opening->unit_id,
            'registration_opening_id' => $opening->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => $nik,
            'full_name' => $name,
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2012-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);
    }
}
