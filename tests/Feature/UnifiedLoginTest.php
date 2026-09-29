<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\PortalDestinationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_login_page_is_the_single_sign_in_entry_point(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke SPMB Taruna Bakti')
            ->assertSee('Email atau Username')
            ->assertSee('Kode Keamanan')
            ->assertSee('Muat kode baru')
            ->assertSee('Satu halaman masuk untuk pendaftar, TU, Admin Unit, dan Super Admin.');

        $this->get('/admin/login')->assertRedirect('/login');
        $this->get('/pendaftar/login')->assertRedirect('/login');
    }

    public function test_all_staff_roles_resolve_to_admin_and_applicant_resolves_to_applicant_panel(): void
    {
        $resolver = app(PortalDestinationService::class);

        foreach (['super_admin', 'admin_unit', 'tu'] as $role) {
            $user = $this->userWithRole($role);
            $this->assertSame('/admin', $resolver->pathFor($user));
            $this->assertSame('/admin', $resolver->profilePathFor($user));
        }

        $applicant = $this->userWithRole('pendaftar');
        $this->assertSame('/pendaftar', $resolver->pathFor($applicant));
        $this->assertSame('/pendaftar/profile', $resolver->profilePathFor($applicant));
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

    public function test_unified_login_accepts_username_and_redirects_staff_to_admin(): void
    {
        $user = $this->userWithRole('tu', [
            'username' => 'staff.tu',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->invokeLogin([
            'email' => 'STAFF.TU',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_unified_login_accepts_email_and_redirects_applicant_to_applicant_panel(): void
    {
        $user = $this->userWithRole('pendaftar', [
            'email' => 'parent@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->invokeLogin([
            'email' => 'parent@example.test',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/pendaftar');
        $this->assertAuthenticatedAs($user);
    }

    public function test_cross_panel_intended_url_is_discarded_after_login(): void
    {
        $staff = $this->userWithRole('admin_unit', [
            'email' => 'admin-unit@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->app['session.store']->put('url.intended', url('/pendaftar/status/not-for-staff'));

        $response = $this->invokeLogin([
            'email' => 'admin-unit@example.test',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($staff);
        $this->assertNull($this->app['session.store']->get('url.intended'));
    }

    public function test_matching_intended_url_is_preserved_after_login(): void
    {
        $staff = $this->userWithRole('super_admin', [
            'email' => 'root@example.test',
            'password' => Hash::make('secret-password'),
        ]);
        $intended = url('/admin/users');

        $this->app['session.store']->put('url.intended', $intended);

        $response = $this->invokeLogin([
            'email' => 'root@example.test',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect($intended);
        $this->assertAuthenticatedAs($staff);
    }

    public function test_inactive_user_cannot_authenticate_through_unified_login(): void
    {
        $user = $this->userWithRole('tu', [
            'email' => 'inactive@example.test',
            'password' => Hash::make('secret-password'),
            'is_active' => false,
        ]);

        try {
            $this->invokeLogin([
                'email' => 'inactive@example.test',
                'password' => 'secret-password',
            ]);

            $this->fail('Inactive account should not authenticate.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertGuest();
            $this->assertNotSame(Auth::id(), $user->id);
        }
    }

    private function invokeLogin(array $data)
    {
        $session = $this->app['session.store'];
        $session->start();

        $request = LoginRequest::create(
            '/login',
            'POST',
            $data + ['remember' => false],
            [],
            [],
            ['REMOTE_ADDR' => '127.0.0.1'],
        );
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setLaravelSession($session);

        return $this->app->call(
            [app(AuthenticatedSessionController::class), 'store'],
            ['request' => $request],
        );
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
