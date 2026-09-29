<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\AccountConsentSettings;
use App\Filament\Applicant\Pages\Auth\Register as ApplicantRegister;
use App\Models\AccountConsentPolicy;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AccountConsentService;
use Database\Seeders\ShieldSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class AccountConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_and_public_legal_pages_show_the_active_account_policy(): void
    {
        $policy = app(AccountConsentService::class)->current();

        $this->get('/pendaftar/register')
            ->assertOk()
            ->assertSee('Persetujuan Akun')
            ->assertSee($policy->required_confirmation_text)
            ->assertSee('Ketentuan Penggunaan')
            ->assertSee('Kebijakan Privasi')
            ->assertSee($policy->marketing_text)
            ->assertSee('Persetujuan informasi/promosi bersifat opsional.');

        $this->get('/legal/terms')
            ->assertOk()
            ->assertSee($policy->terms_title)
            ->assertSee('Pembuatan akun tidak dengan sendirinya berarti pendaftaran');

        $this->get('/legal/privacy')
            ->assertOk()
            ->assertSee($policy->privacy_title)
            ->assertSee('Undang-Undang Nomor 27 Tahun 2022');
    }

    public function test_signup_consent_service_records_required_snapshots_and_marketing_only_when_opted_in(): void
    {
        $service = app(AccountConsentService::class);
        $policy = $service->current();

        $withoutMarketing = User::factory()->create();
        $service->recordSignupConsents(
            $withoutMarketing,
            $policy,
            false,
            '127.0.0.1',
            'PHPUnit',
        );

        $this->assertSame(
            ['privacy_policy', 'terms_of_service'],
            $withoutMarketing->consents()->orderBy('consent_type')->pluck('consent_type')->all(),
        );

        $withMarketing = User::factory()->create();
        $service->recordSignupConsents(
            $withMarketing,
            $policy,
            true,
            '127.0.0.1',
            'PHPUnit',
        );

        $this->assertSame(
            ['marketing_consent', 'privacy_policy', 'terms_of_service'],
            $withMarketing->consents()->orderBy('consent_type')->pluck('consent_type')->all(),
        );

        foreach ($withMarketing->consents as $consent) {
            $this->assertSame($policy->id, $consent->account_consent_policy_id);
            $this->assertSame(64, strlen($consent->content_hash));
            $this->assertNotNull($consent->accepted_at);
            $this->assertSame('127.0.0.1', $consent->ip_address);
            $this->assertSame('PHPUnit', $consent->user_agent);
        }
    }

    public function test_filament_signup_handler_creates_user_role_and_required_consent_snapshots(): void
    {
        $policy = app(AccountConsentService::class)->current();

        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));
        $component = Livewire::test(ApplicantRegister::class)->instance();
        $method = new ReflectionMethod(ApplicantRegister::class, 'handleRegistration');

        /** @var User $user */
        $user = $method->invoke($component, [
            'name' => 'Pendaftar Consent',
            'email' => 'consent@example.test',
            'password' => 'Password-12345',
            'password_confirmation' => 'Password-12345',
            'account_consent_policy_uuid' => $policy->uuid,
            'account_consent_accepted' => true,
            'marketing_consent' => false,
        ]);

        $this->assertTrue($user->hasRole('pendaftar'));
        $this->assertSame('user', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'consent@example.test',
        ]);
        $this->assertSame(
            ['privacy_policy', 'terms_of_service'],
            UserConsent::query()
                ->where('user_id', $user->id)
                ->orderBy('consent_type')
                ->pluck('consent_type')
                ->all(),
        );
    }

    public function test_super_admin_can_publish_sanitized_policy_and_admin_unit_cannot_edit_global_policy(): void
    {
        $this->seed(ShieldSeeder::class);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AccountConsentSettings::class)
            ->assertSee('Kebijakan & Persetujuan Akun')
            ->fillForm([
                'terms_title' => 'Ketentuan Akun Baru',
                'terms_content' => '<script>alert(1)</script><p onclick="alert(2)">Ketentuan aman.</p>',
                'privacy_title' => 'Privasi Akun Baru',
                'privacy_content' => '<p>Privasi versi baru.</p>',
                'required_confirmation_text' => 'Saya menerima ketentuan akun baru.',
                'marketing_enabled' => true,
                'marketing_text' => 'Saya bersedia menerima informasi program.',
            ])
            ->call('publish')
            ->assertHasNoFormErrors();

        $published = AccountConsentPolicy::query()
            ->where('status', 'published')
            ->orderByDesc('version')
            ->firstOrFail();

        $this->assertSame(2, $published->version);
        $this->assertSame('Ketentuan Akun Baru', $published->terms_title);
        $this->assertStringNotContainsString('<script', $published->terms_content);
        $this->assertStringNotContainsString('onclick=', $published->terms_content);
        $this->assertStringContainsString('<p>Ketentuan aman.</p>', $published->terms_content);

        $adminUnit = User::factory()->create([
            'role' => 'admin_unit',
            'is_active' => true,
        ]);
        $adminUnit->assignRole('admin_unit');

        $this->actingAs($adminUnit);
        $this->assertFalse(AccountConsentSettings::canAccess());
    }
}
