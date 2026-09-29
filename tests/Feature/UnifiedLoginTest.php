<?php

namespace Tests\Feature;

use App\Filament\Auth\Pages\Login as UnifiedLogin;
use App\Models\User;
use App\Services\PortalDestinationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use MortezaAshrafi\FilamentShieldCaptcha\CaptchaManager;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
    }

    public function test_session_cookie_is_root_scoped_for_unified_login_and_livewire(): void
    {
        $this->assertSame('/', config('session.path'));
    }

    public function test_unified_login_page_is_native_filament_and_has_no_role_explanation_sentence(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke SPMB Taruna Bakti')
            ->assertSee('Email atau Username')
            ->assertSee('Kode Keamanan')
            ->assertSee('fi-simple-page', false)
            ->assertSee('fi-fo-shield-captcha', false)
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

    public function test_native_filament_login_accepts_username_and_redirects_staff_to_admin(): void
    {
        $user = $this->userWithRole('tu', [
            'username' => 'staff.tu',
            'password' => Hash::make('secret-password'),
        ]);

        $component = Livewire::test(UnifiedLogin::class);
        $captcha = $this->captchaAnswer($component->instance());

        $component
            ->fillForm([
                'email' => 'STAFF.TU',
                'password' => 'secret-password',
                'remember' => false,
                'captcha' => $captcha,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    }

    public function test_native_filament_login_accepts_email_and_redirects_applicant_to_applicant_panel(): void
    {
        $user = $this->userWithRole('pendaftar', [
            'email' => 'parent@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        $component = Livewire::test(UnifiedLogin::class);
        $captcha = $this->captchaAnswer($component->instance());

        $component
            ->fillForm([
                'email' => 'parent@example.test',
                'password' => 'secret-password',
                'remember' => false,
                'captcha' => $captcha,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/pendaftar');

        $this->assertAuthenticatedAs($user);
    }

    public function test_cross_panel_intended_url_is_discarded_after_native_filament_login(): void
    {
        $staff = $this->userWithRole('admin_unit', [
            'email' => 'admin-unit@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        session()->put('url.intended', url('/pendaftar/status/not-for-staff'));

        $component = Livewire::test(UnifiedLogin::class);
        $captcha = $this->captchaAnswer($component->instance());

        $component
            ->fillForm([
                'email' => 'admin-unit@example.test',
                'password' => 'secret-password',
                'remember' => false,
                'captcha' => $captcha,
            ])
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($staff);
        $this->assertNull(session()->get('url.intended'));
    }

    public function test_inactive_user_cannot_authenticate_through_native_filament_login(): void
    {
        $this->userWithRole('tu', [
            'email' => 'inactive@example.test',
            'password' => Hash::make('secret-password'),
            'is_active' => false,
        ]);

        $component = Livewire::test(UnifiedLogin::class);
        $captcha = $this->captchaAnswer($component->instance());

        $component
            ->fillForm([
                'email' => 'inactive@example.test',
                'password' => 'secret-password',
                'remember' => false,
                'captcha' => $captcha,
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    private function captchaAnswer(UnifiedLogin $page): string
    {
        $field = collect($page->form->getFlatComponents())
            ->first(fn ($component): bool => method_exists($component, 'getName')
                && $component->getName() === 'captcha');

        $this->assertNotNull($field, 'Native Filament login captcha field was not found.');

        $manager = app(CaptchaManager::class);
        $options = $manager->optionsFromConfig([]);

        return $manager->ensureChallenge(
            $field->getCaptchaContextKey(),
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
