<?php

namespace Tests\Feature;

use App\Filament\Applicant\Pages\Auth\Register as ApplicantRegister;
use App\Filament\Applicant\Resources\RegistrationResource as ApplicantRegistrationResource;
use App\Filament\Applicant\Resources\RegistrationResource\Pages\CreateRegistration;
use App\Models\EducationLevel;
use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ApplicantVerifyEmail;
use App\Services\ApplicantEmailVerificationUrl;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicantPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_auth_urls_redirect_to_canonical_applicant_portal(): void
    {
        $this->get('/login')->assertRedirect('/pendaftar/login');
        $this->get('/register')->assertRedirect('/pendaftar/register');
        $this->get('/forgot-password')->assertRedirect('/pendaftar/password-reset/request');
    }

    public function test_canonical_applicant_auth_pages_are_available(): void
    {
        $this->get('/pendaftar/login')->assertOk();
        $this->get('/pendaftar/register')
            ->assertOk()
            ->assertSee('Nama Lengkap');
        $this->get('/pendaftar/password-reset/request')->assertOk();
    }

    public function test_applicant_registration_handler_assigns_pendaftar_role_and_leaves_email_unverified(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        $page = app(ApplicantRegister::class);
        $method = new ReflectionMethod($page, 'handleRegistration');
        $method->setAccessible(true);

        /** @var User $user */
        $user = $method->invoke($page, [
            'name' => 'Orang Tua',
            'email' => 'orangtua@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->assertInstanceOf(Model::class, $user);
        $this->assertSame('user', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('pendaftar'));
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_higher_education_self_registration_still_shows_parent_data(): void
    {
        $level = EducationLevel::query()->where('code', 'S1')->firstOrFail();
        $unit = Unit::create([
            'name' => 'Taruna Bakti University',
            'code' => 'TBU',
            'institution_type' => 'university',
            'is_active' => true,
        ]);
        $program = StudyProgram::create([
            'unit_id' => $unit->id,
            'education_level_id' => $level->id,
            'code' => 'IF',
            'name' => 'Informatika',
            'degree_level' => 'S1',
            'is_active' => true,
        ]);
        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'study_program_id' => $program->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);

        $applicant = $this->userWithRole('pendaftar');
        $this->actingAs($applicant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::withQueryParams(['opening' => $opening->uuid])
            ->test(CreateRegistration::class)
            ->fillForm(['registrant_type' => 'self'])
            ->assertSee('Data Orang Tua')
            ->assertSee('Nama Ayah')
            ->assertSee('Nama Ibu');
    }

    public function test_registration_sends_queueable_filament_verification_email(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        $user = $this->userWithRole('pendaftar', ['email_verified_at' => null]);
        $page = app(ApplicantRegister::class);
        $method = new ReflectionMethod($page, 'sendEmailVerificationNotification');
        $method->setAccessible(true);
        $method->invoke($page, $user);

        Notification::assertSentTo(
            $user,
            ApplicantVerifyEmail::class,
            function (ApplicantVerifyEmail $notification) use ($user): bool {
                return $notification instanceof ShouldQueue
                    && str_contains($notification->url, '/pendaftar/email-verification/uuid-verify/')
                    && str_contains($notification->url, $user->uuid)
                    && ! str_contains($notification->url, '/'.$user->id.'/');
            },
        );
    }

    public function test_unverified_applicant_is_forced_to_verification_prompt(): void
    {
        $applicant = $this->userWithRole('pendaftar', ['email_verified_at' => null]);

        $response = $this->actingAs($applicant)->get('/pendaftar');

        $response->assertRedirect();
        $this->assertStringContainsString(
            '/pendaftar/email-verification/prompt',
            (string) $response->headers->get('Location'),
        );

        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $this->actingAs($applicant)
            ->get(Filament::getEmailVerificationPromptUrl())
            ->assertOk();
    }

    public function test_signed_verification_link_verifies_email_and_is_audited(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $applicant = $this->userWithRole('pendaftar', ['email_verified_at' => null]);

        $url = app(ApplicantEmailVerificationUrl::class)->for($applicant);

        $this->actingAs($applicant)->get($url)->assertRedirect();
        $this->assertStringContainsString($applicant->uuid, $url);
        $this->assertStringNotContainsString('/'.$applicant->id.'/', $url);

        $this->assertTrue($applicant->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.email_verified',
            'user_id' => $applicant->id,
        ]);
    }

    public function test_legacy_numeric_verification_url_is_not_accepted(): void
    {
        $applicant = $this->userWithRole('pendaftar', ['email_verified_at' => null]);

        $this->actingAs($applicant)
            ->get('/pendaftar/email-verification/verify/'.$applicant->id.'/'.sha1($applicant->email))
            ->assertNotFound();
    }

    public function test_unverified_applicant_cannot_create_registration_even_if_opening_exists(): void
    {
        $unit = Unit::create([
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA',
            'is_active' => true,
        ]);

        RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 300000,
            'status' => 'open',
        ]);

        $applicant = $this->userWithRole('pendaftar', ['email_verified_at' => null]);
        $this->actingAs($applicant);

        $this->assertFalse(ApplicantRegistrationResource::canCreate());

        $applicant->markEmailAsVerified();
        $this->assertTrue(ApplicantRegistrationResource::canCreate());
    }

    public function test_dashboard_alias_routes_users_to_the_correct_portal(): void
    {
        $applicant = $this->userWithRole('pendaftar');
        $this->actingAs($applicant)->get('/dashboard')->assertRedirect('/pendaftar');

        auth()->logout();

        $tu = $this->userWithRole('tu', ['role' => 'tu']);
        $this->actingAs($tu)->get('/dashboard')->assertRedirect('/admin');
    }

    public function test_applicant_can_access_applicant_dashboard_and_profile(): void
    {
        $applicant = $this->userWithRole('pendaftar');

        $this->actingAs($applicant)->get('/pendaftar')->assertOk();
        $this->actingAs($applicant)->get('/pendaftar/profile')->assertOk();
    }

    public function test_legacy_applicant_mutation_endpoints_are_retired(): void
    {
        $applicant = $this->userWithRole('pendaftar');
        $this->actingAs($applicant);

        $this->post('/registration')->assertNotFound();
        $this->post('/registration/1/payment')->assertNotFound();
        $this->post('/registration/1/documents')->assertNotFound();
        $this->patch('/profile')->assertStatus(405);
        $this->delete('/profile')->assertStatus(405);
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
