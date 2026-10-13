<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ApplicantResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicantPasswordResetSignedUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        URL::forceRootUrl(null);

        parent::tearDown();
    }

    public function test_new_applicant_reset_link_works_behind_different_origin_host_and_scheme(): void
    {
        config()->set('app.url', 'https://pmb.example.test');
        $applicant = $this->applicant();
        $token = Password::broker()->createToken($applicant);

        $notification = new ApplicantResetPassword($token);
        // Filament normally injects its own *absolute* signed link. The
        // applicant notification must replace it with a host-independent one.
        $notification->url = 'https://pmb.example.test/pendaftar/password-reset/reset?signature=old';
        $url = $notification->toMail($applicant)->data()['url'];

        $this->assertStringStartsWith(
            'https://pmb.example.test/pendaftar/password-reset/reset?',
            $url,
        );
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('token='.$token, $url);
        $this->assertTrue(Password::broker()->tokenExists($applicant, $token));

        $this->withServerVariables([
            'HTTP_HOST' => 'origin.internal.test',
            'SERVER_NAME' => 'origin.internal.test',
            'HTTPS' => 'off',
        ])->get($this->requestTarget($url))
            ->assertOk();
    }

    public function test_manual_applicant_password_recovery_also_generates_a_valid_relative_signature(): void
    {
        config()->set('app.url', 'https://pmb.example.test');
        $applicant = $this->applicant();
        $token = Password::broker()->createToken($applicant);

        $notification = new ApplicantResetPassword($token);
        $url = $notification->toMail($applicant)->data()['url'];

        $this->withServerVariables([
            'HTTP_HOST' => 'internal.test',
            'HTTPS' => 'off',
        ])->get($this->requestTarget($url))
            ->assertOk();
    }

    public function test_existing_absolute_signed_filament_link_remains_valid_behind_proxy(): void
    {
        config()->set('app.url', 'https://pmb.example.test');
        URL::forceRootUrl('https://pmb.example.test');
        $applicant = $this->applicant();
        $token = Password::broker()->createToken($applicant);

        // Format of Filament reset URLs issued before this fix.
        $oldUrl = URL::signedRoute(
            'filament.pendaftar.auth.password-reset.reset',
            ['email' => $applicant->email, 'token' => $token],
        );

        $this->withServerVariables([
            'HTTP_HOST' => 'internal.test',
            'SERVER_NAME' => 'internal.test',
            'HTTPS' => 'off',
        ])->get($this->requestTarget($oldUrl))
            ->assertOk();
    }

    public function test_tampered_reset_link_remains_forbidden(): void
    {
        config()->set('app.url', 'https://pmb.example.test');
        $applicant = $this->applicant();
        $notification = new ApplicantResetPassword(Password::broker()->createToken($applicant));
        $url = $notification->toMail($applicant)->data()['url'];

        $tampered = str_replace('token=', 'token=tampered-', $this->requestTarget($url));

        $this->get($tampered)->assertForbidden();
    }

    public function test_other_signed_routes_retain_standard_strict_validation(): void
    {
        Route::get('/test-signed-reset-isolation', fn () => 'valid')
            ->middleware('signed')
            ->name('tests.signed-reset-isolation');
        Route::getRoutes()->refreshNameLookups();
        URL::forceRootUrl('https://public.example.test');

        $url = URL::signedRoute('tests.signed-reset-isolation');

        $this->get('/test-signed-reset-isolation')->assertForbidden();

        // The generic Laravel signed middleware still accepts a valid
        // signature but rejects a modified one. The applicant-only fallback
        // must not weaken validation for unrelated signed routes.
        $target = $this->requestTarget($url);
        $this->get($target)->assertOk();
        $this->get(str_replace('signature=', 'signature=tampered-', $target))
            ->assertForbidden();
    }

    private function applicant(): User
    {
        $role = Role::firstOrCreate(['name' => 'pendaftar', 'guard_name' => 'web']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function requestTarget(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        return $query === '' ? $path : $path.'?'.$query;
    }
}
