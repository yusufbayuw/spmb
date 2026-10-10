<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PortalDestinationService;
use App\Services\UnifiedLoginCaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_cookie_is_root_scoped_for_unified_login(): void
    {
        $this->assertSame('/', config('session.path'));
    }

    public function test_unified_login_page_uses_filament_native_shell_without_livewire_submit(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke '.config('spmb.portal.name', 'SPMB'))
            ->assertSee('Email atau Username')
            ->assertSee('Kode Keamanan')
            ->assertSee('fi-simple-page', false)
            ->assertSee('fi-input-wrp', false)
            ->assertSee('action="/login"', false)
            ->assertDontSee('wire:submit', false)
            ->assertDontSee('Satu halaman masuk untuk pendaftar, TU, Admin Unit, dan Super Admin.')
            ->assertDontSee('Portal akan dipilih otomatis berdasarkan hak akses akun.');

        $this->get('/admin/login')->assertRedirect('/login');
        $this->get('/pendaftar/login')->assertRedirect('/login');
    }

    public function test_all_staff_roles_resolve_to_admin_and_applicant_resolves_to_applicant_panel(): void
    {
        $resolver = app(PortalDestinationService::class);

        foreach (['super_admin', 'admin_unit', 'tu'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertSame('/admin', $resolver->pathFor($user));
            $this->assertSame('/admin/profile', $resolver->profilePathFor($user));
        }

        $applicant = $this->userWithRole('pendaftar');
        $this->assertSame('/pendaftar', $resolver->pathFor($applicant));
        $this->assertSame('/pendaftar/profile', $resolver->profilePathFor($applicant));
    }

    public function test_staff_login_populates_auth_version_for_rotated_credentials(): void
    {
        $staff = $this->userWithRole('tu', [
            'username' => 'rotated.tu',
            'password' => Hash::make('CurrentPassword123!'),
            'auth_version' => 3,
        ]);

        $this->get('/login')->assertOk();

        $this->post('/login', [
            'email' => 'rotated.tu',
            'password' => 'CurrentPassword123!',
            'captcha' => $this->captchaAnswer(),
        ])->assertRedirect('/admin')->assertSessionHas('spmb_staff_auth_version', 3);

        $this->get('/admin')->assertOk();
        $this->assertAuthenticatedAs($staff);
    }

    public function test_staff_role_wins_for_a_dual_role_account(): void
    {
        $user = $this->userWithRole('pendaftar');
        $user->assignRole(Role::firstOrCreate([
            'name' => 'tu',
            'guard_name' => 'web',
        ]));

        $this->assertSame('/admin', app(PortalDestinationService::class)->pathFor($user));
    }

    public function test_standard_post_login_accepts_username_and_redirects_staff_to_admin(): void
    {
        $user = $this->userWithRole('tu', [
            'username' => 'staff.tu',
            'password' => Hash::make('secret-password'),
        ]);

        $this->get('/login')->assertOk();

        $this->post('/login', [
            'email' => 'STAFF.TU',
            'password' => 'secret-password',
            'captcha' => $this->captchaAnswer(),
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_standard_post_login_accepts_email_and_redirects_applicant_to_applicant_panel(): void
    {
        $user = $this->userWithRole('pendaftar', [
            'email' => 'parent@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->get('/login')->assertOk();

        $this->post('/login', [
            'email' => 'parent@example.test',
            'password' => 'secret-password',
            'captcha' => $this->captchaAnswer(),
        ])->assertRedirect('/pendaftar');

        $this->assertAuthenticatedAs($user);
    }

    public function test_cross_panel_intended_url_is_discarded_after_standard_post_login(): void
    {
        $staff = $this->userWithRole('admin_unit', [
            'email' => 'admin-unit@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->withSession([
            'url.intended' => url('/pendaftar/status/not-for-staff'),
        ])->get('/login')->assertOk();

        $this->post('/login', [
            'email' => 'admin-unit@example.test',
            'password' => 'secret-password',
            'captcha' => $this->captchaAnswer(),
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($staff);
        $this->assertNull(session()->get('url.intended'));
    }

    public function test_matching_intended_url_is_preserved_after_standard_post_login(): void
    {
        $staff = $this->userWithRole('super_admin', [
            'email' => 'root@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        $intended = url('/admin/users');

        $this->withSession([
            'url.intended' => $intended,
        ])->get('/login')->assertOk();

        $this->post('/login', [
            'email' => 'root@example.test',
            'password' => 'secret-password',
            'captcha' => $this->captchaAnswer(),
        ])->assertRedirect($intended);

        $this->assertAuthenticatedAs($staff);
    }

    public function test_inactive_user_cannot_authenticate_through_standard_post_login(): void
    {
        $this->userWithRole('tu', [
            'email' => 'inactive@example.test',
            'password' => Hash::make('secret-password'),
            'is_active' => false,
        ]);

        $this->get('/login')->assertOk();

        $this->post('/login', [
            'email' => 'inactive@example.test',
            'password' => 'secret-password',
            'captcha' => $this->captchaAnswer(),
        ])->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    private function captchaAnswer(): string
    {
        $service = app(UnifiedLoginCaptcha::class);
        $manager = app(\MortezaAshrafi\FilamentShieldCaptcha\CaptchaManager::class);
        $options = $manager->optionsFromConfig([]);

        return $manager->ensureChallenge(
            $service->contextKey(),
            $options,
        )->answer;
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create($attributes + [
            'role' => $roleName === 'pendaftar' ? 'user' : $roleName,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
