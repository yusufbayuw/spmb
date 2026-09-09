<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Filament\Applicant\Pages\Auth\Login as ApplicantLogin;
use App\Filament\Applicant\Pages\Auth\Register as ApplicantRegister;
use App\Filament\Applicant\Pages\Auth\RequestPasswordReset;
use App\Filament\Applicant\Pages\Auth\ResetPassword;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthCaptchaTest extends TestCase
{
    use RefreshDatabase;

    public function test_panels_use_the_captcha_protected_auth_pages(): void
    {
        $admin = Filament::getPanel('admin');
        $applicant = Filament::getPanel('pendaftar');

        $this->assertSame(AdminLogin::class, $admin->getLoginRouteAction());
        $this->assertSame(ApplicantLogin::class, $applicant->getLoginRouteAction());
        $this->assertSame(ApplicantRegister::class, $applicant->getRegistrationRouteAction());
        $this->assertSame(RequestPasswordReset::class, $applicant->getRequestPasswordResetRouteAction());
        $this->assertSame(ResetPassword::class, $applicant->getResetPasswordRouteAction());
    }

    public function test_admin_login_renders_local_captcha(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Kode Keamanan')
            ->assertSee('fi-fo-shield-captcha', false);
    }

    public function test_applicant_login_registration_and_reset_request_render_local_captcha(): void
    {
        foreach ([
            '/pendaftar/login',
            '/pendaftar/register',
            '/pendaftar/password-reset/request',
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Kode Keamanan')
                ->assertSee('fi-fo-shield-captcha', false);
        }
    }

    public function test_applicant_reset_password_page_renders_local_captcha(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $user = User::factory()->create();
        $url = Filament::getResetPasswordUrl('test-reset-token', $user);

        $this->get($url)
            ->assertOk()
            ->assertSee('Kode Keamanan')
            ->assertSee('fi-fo-shield-captcha', false);
    }

    public function test_captcha_defaults_prioritize_readability_and_avoid_ambiguous_characters(): void
    {
        $this->assertSame('custom', config('filament-shield-captcha.defaults.mode'));
        $this->assertSame('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', config('filament-shield-captcha.defaults.charset'));
        $this->assertFalse(config('filament-shield-captcha.defaults.case_sensitive'));
        $this->assertSame(5, config('filament-shield-captcha.defaults.length'));
        $this->assertSame(1, config('filament-shield-captcha.defaults.noise.level'));
        $this->assertSame(5, config('filament-shield-captcha.store.max_attempts'));
        $this->assertSame(300, config('filament-shield-captcha.store.ttl'));
    }
}
