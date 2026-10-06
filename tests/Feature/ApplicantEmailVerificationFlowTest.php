<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApplicantEmailVerificationUrl;
use App\Support\SpmbOperationalMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicantEmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        URL::forceRootUrl(null);
        config()->set('spmb.operations.mode', SpmbOperationalMode::MIXED);

        parent::tearDown();
    }

    public function test_higher_education_verification_link_is_not_bound_to_request_host(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);
        config()->set('app.url', 'https://pmb.example.test');

        $applicant = $this->applicant(['email_verified_at' => null]);
        $url = app(ApplicantEmailVerificationUrl::class)->for($applicant);

        $this->assertStringStartsWith(
            'https://pmb.example.test/pendaftar/email-verification/uuid-verify/',
            $url,
        );

        $target = $this->requestTarget($url);

        $this->withServerVariables([
            'HTTP_HOST' => 'origin.internal.test',
            'SERVER_NAME' => 'origin.internal.test',
            'HTTPS' => 'on',
        ])->actingAs($applicant)
            ->get($target)
            ->assertRedirect();

        $this->assertTrue($applicant->fresh()->hasVerifiedEmail());
    }

    public function test_preexisting_absolute_signed_link_can_still_verify_behind_a_different_host(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);
        config()->set('app.url', 'https://pmb.example.test');
        URL::forceRootUrl('https://pmb.example.test');

        $applicant = $this->applicant(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'applicant.email-verification.verify',
            now()->addMinutes(60),
            [
                'user' => $applicant,
                'hash' => sha1($applicant->getEmailForVerification()),
            ],
        );

        $this->withServerVariables([
            'HTTP_HOST' => 'origin.internal.test',
            'SERVER_NAME' => 'origin.internal.test',
            'HTTPS' => 'on',
        ])->actingAs($applicant)
            ->get($this->requestTarget($url))
            ->assertRedirect();

        $this->assertTrue($applicant->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_opened_under_another_account_redirects_to_login_instead_of_403(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);
        config()->set('app.url', 'https://pmb.example.test');

        $targetApplicant = $this->applicant([
            'email' => 'target@example.test',
            'email_verified_at' => null,
        ]);
        $otherApplicant = $this->applicant([
            'email' => 'other@example.test',
        ]);

        $url = app(ApplicantEmailVerificationUrl::class)->for($targetApplicant);

        $this->actingAs($otherApplicant)
            ->get($this->requestTarget($url))
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertFalse($targetApplicant->fresh()->hasVerifiedEmail());
        $this->assertStringContainsString(
            '/pendaftar/email-verification/uuid-verify/'.$targetApplicant->uuid,
            (string) session('url.intended'),
        );
    }

    public function test_tampered_verification_link_is_still_rejected(): void
    {
        config()->set('spmb.operations.mode', SpmbOperationalMode::HIGHER_EDUCATION);
        config()->set('app.url', 'https://pmb.example.test');

        $applicant = $this->applicant(['email_verified_at' => null]);
        $url = app(ApplicantEmailVerificationUrl::class)->for($applicant);
        $target = str_replace(
            sha1($applicant->getEmailForVerification()),
            str_repeat('0', 40),
            $this->requestTarget($url),
        );

        $this->actingAs($applicant)
            ->get($target)
            ->assertForbidden();

        $this->assertFalse($applicant->fresh()->hasVerifiedEmail());
    }

    private function applicant(array $attributes = []): User
    {
        $role = Role::firstOrCreate([
            'name' => 'pendaftar',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create($attributes + [
            'role' => 'user',
            'is_active' => true,
        ]);
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
