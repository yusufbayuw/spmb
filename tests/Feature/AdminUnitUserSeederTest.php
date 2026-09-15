<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AdminUnitRoleSeeder;
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

        $accounts = [
            'DC' => ['username' => 'admin.daycare', 'email' => 'admin.daycare@tarunabakti.sch.id'],
            'KB' => ['username' => 'admin.kb', 'email' => 'admin.kb@tarunabakti.sch.id'],
            'TK' => ['username' => 'admin.tk', 'email' => 'admin.tk@tarunabakti.sch.id'],
            'SD' => ['username' => 'admin.sd', 'email' => 'admin.sd@tarunabakti.sch.id'],
            'SMP' => ['username' => 'admin.smp', 'email' => 'admin.smp@tarunabakti.sch.id'],
            'SMA' => ['username' => 'admin.sma', 'email' => 'admin.sma@tarunabakti.sch.id'],
            'TBU' => ['username' => 'admin.tbu', 'email' => 'admin.tbu@tbu.ac.id'],
        ];

        foreach ($accounts as $unitCode => $identity) {
            $unit = Unit::query()->where('code', $unitCode)->firstOrFail();
            $user = User::query()->where('username', $identity['username'])->firstOrFail();

            $this->assertSame($identity['email'], $user->email);
            $this->assertSame($unit->id, $user->unit_id);
            $this->assertSame('admin_unit', $user->role);
            $this->assertTrue($user->is_active);
            $this->assertTrue($user->hasRole('admin_unit'));
            $this->assertTrue($user->can('view_any_registrationopening'));
            $this->assertTrue($user->can('view_any_virtualaccount'));
            $this->assertTrue($user->can('view_any_auditlog'));
            $this->assertTrue(Hash::check('password123', $user->password));
        }

        $this->assertSame(7, User::query()->whereNotNull('username')->where('username', 'like', 'admin.%')->count());
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

    public function test_it_migrates_the_previous_adminunit_email_without_creating_a_duplicate_or_resetting_password(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(AdminUnitRoleSeeder::class);

        $unit = Unit::query()->where('code', 'SD')->firstOrFail();
        $legacy = User::create([
            'name' => 'Admin Unit SD',
            'email' => 'adminunit.sd@tarunabakti.sch.id',
            'password' => Hash::make('keep-this-password'),
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $legacy->syncRoles(['admin_unit']);

        $this->seed(AdminUnitUserSeeder::class);

        $legacy->refresh();

        $this->assertSame('admin.sd', $legacy->username);
        $this->assertSame('admin.sd@tarunabakti.sch.id', $legacy->email);
        $this->assertTrue(Hash::check('keep-this-password', $legacy->password));
        $this->assertSame(1, User::query()->where('username', 'admin.sd')->count());
        $this->assertDatabaseMissing('users', ['email' => 'adminunit.sd@tarunabakti.sch.id']);
    }
}
