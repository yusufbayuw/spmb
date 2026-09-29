<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AdminUnitUserSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUnitUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_admin_unit_accounts_for_every_seeded_unit_with_unique_usernames_and_access(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(AdminUnitUserSeeder::class);

        foreach (Unit::query()->forOperationalMode()->get() as $unit) {
            $suffix = mb_strtolower($unit->code);
            $user = User::query()->where('username', 'admin.'.$suffix)->firstOrFail();

            $this->assertSame('admin.'.$suffix.'@example.test', $user->email);
            $this->assertSame($unit->id, $user->unit_id);
            $this->assertSame('admin_unit', $user->role);
            $this->assertTrue($user->is_active);
            $this->assertTrue($user->hasRole('admin_unit'));
            $this->assertTrue($user->can('view_any_registrationopening'));
            $this->assertTrue($user->can('view_any_virtualaccount'));
            $this->assertTrue($user->can('view_any_auditlog'));
            $this->assertTrue(Hash::check('password123', $user->password));
        }

        $this->assertSame(7, User::query()->where('role', 'admin_unit')->count());
        $this->assertSame(7, User::query()->where('role', 'admin_unit')->distinct()->count('username'));
    }

    public function test_it_is_idempotent_and_does_not_reset_existing_password(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(AdminUnitUserSeeder::class);

        $user = User::query()->where('username', 'admin.sd')->firstOrFail();
        $user->forceFill(['password' => Hash::make('changed-for-local-dev')])->save();

        $this->seed(AdminUnitUserSeeder::class);

        $user->refresh();

        $this->assertSame(7, User::query()->where('role', 'admin_unit')->count());
        $this->assertTrue(Hash::check('changed-for-local-dev', $user->password));
        $this->assertTrue($user->hasRole('admin_unit'));
    }

    public function test_it_does_not_overwrite_an_existing_account_with_the_same_demo_email(): void
    {
        $this->seed(UnitSeeder::class);

        $unit = Unit::query()->where('code', 'SD')->firstOrFail();
        $existing = User::create([
            'name' => 'Nama yang Dipertahankan',
            'username' => 'existing-admin',
            'email' => 'admin.sd@example.test',
            'password' => Hash::make('keep-this-password'),
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'email_verified_at' => now(),
            'is_active' => false,
        ]);

        $this->seed(AdminUnitUserSeeder::class);

        $existing->refresh();

        $this->assertSame('Nama yang Dipertahankan', $existing->name);
        $this->assertSame('existing-admin', $existing->username);
        $this->assertFalse($existing->is_active);
        $this->assertTrue(Hash::check('keep-this-password', $existing->password));
    }
}
