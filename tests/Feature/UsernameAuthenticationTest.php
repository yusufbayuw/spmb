<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Filament\Applicant\Pages\Auth\Login as ApplicantLogin;
use App\Filament\Support\Concerns\AuthenticatesWithEmailOrUsername;
use App\Models\User;
use Database\Seeders\AdminUnitUserSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UsernameAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_unit_can_authenticate_with_username_or_email(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(AdminUnitUserSeeder::class);

        $this->assertTrue(Auth::attempt([
            'username' => 'admin.sd',
            'password' => 'password123',
        ]));

        Auth::logout();

        $this->assertTrue(Auth::attempt([
            'email' => 'admin.sd@tarunabakti.sch.id',
            'password' => 'password123',
        ]));
    }

    public function test_login_pages_map_non_email_identifiers_to_username_and_keep_email_login(): void
    {
        $this->assertContains(AuthenticatesWithEmailOrUsername::class, class_uses(AdminLogin::class));
        $this->assertContains(AuthenticatesWithEmailOrUsername::class, class_uses(ApplicantLogin::class));

        $mapper = new class
        {
            use AuthenticatesWithEmailOrUsername;

            public function credentials(array $data): array
            {
                return $this->getCredentialsFromFormData($data);
            }
        };

        $this->assertSame([
            'username' => 'admin.sd',
            'password' => 'secret',
        ], $mapper->credentials([
            'email' => 'ADMIN.SD',
            'password' => 'secret',
        ]));

        $this->assertSame([
            'email' => 'person@example.com',
            'password' => 'secret',
        ], $mapper->credentials([
            'email' => 'person@example.com',
            'password' => 'secret',
        ]));
    }

    public function test_username_is_normalized_and_unique(): void
    {
        User::factory()->create(['username' => 'Example.User']);

        $this->assertDatabaseHas('users', ['username' => 'example.user']);

        $this->expectException(QueryException::class);

        User::factory()->create(['username' => 'example.user']);
    }
}
