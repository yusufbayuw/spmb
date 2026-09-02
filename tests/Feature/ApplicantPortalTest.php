<?php

namespace Tests\Feature;

use App\Filament\Applicant\Pages\Auth\Register as ApplicantRegister;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        $this->get('/pendaftar/register')->assertOk();
        $this->get('/pendaftar/password-reset/request')->assertOk();
    }

    public function test_applicant_registration_handler_assigns_pendaftar_role(): void
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
