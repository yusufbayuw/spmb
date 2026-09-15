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

    public function test_it_creates_admin_unit_accounts_for_every_seeded_unit_with_access(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(AdminUnitUserSeeder::class);

        $accounts = [
            'DC' => 'adminunit.dc@tarunabakti.sch.id',
            'KB' => 'adminunit.kb@tarunabakti.sch.id',
            'TK' => 'adminunit.tk@tarunabakti.sch.id',
            'SD' => 'adminunit.sd@tarunabakti.sch.id',
            'SMP' => 'adminunit.smp@tarunabakti.sch.id',
            'SMA' => 'adminunit.sma@tarunabakti.sch.id',
            'TBU' => 'adminunit.tbu@tbu.ac.id',
        ];

        foreach ($accounts as $unitCode => $email) {
            $unit = Unit::query()->where('code', $unitCode)->firstOrFail();
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($unit->id, $user->unit_id);
            $this->assertSame('admin_unit', $user->role);
            $this->assertTrue($user->is_active);
            $this->assertTrue($user->hasRole('admin_unit'));
            $this->assertTrue($user->can('view_any_registrationopening'));
            $this->assertTrue($user->can('view_any_virtualaccount'));
            $this->assertTrue($user->can('view_any_auditlog'));
            $this->assertTrue(Hash::check('password123', $user->password));
        }
    }

    public function test_it_is_idempotent_and_does_not_reset_existing_password(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(AdminUnitUserSeeder::class);

        $user = User::query()->where('email', 'adminunit.sd@tarunabakti.sch.id')->firstOrFail();
        $user->forceFill(['password' => Hash::make('changed-for-local-dev')])->save();

        $this->seed(AdminUnitUserSeeder::class);

        $user->refresh();

        $this->assertSame(7, User::query()->where('role', 'admin_unit')->count());
        $this->assertTrue(Hash::check('changed-for-local-dev', $user->password));
        $this->assertTrue($user->hasRole('admin_unit'));
    }
}
