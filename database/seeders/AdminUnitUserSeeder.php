<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUnitUserSeeder extends Seeder
{
    public function run(): void
    {
        // Keep this seeder runnable on its own. The role seeder is idempotent
        // and guarantees the canonical Admin Unit/TU permission matrix exists.
        $this->call(AdminUnitRoleSeeder::class);

        $staff = [
            'DC' => ['label' => 'Daycare', 'email' => 'adminunit.dc@tarunabakti.sch.id'],
            'KB' => ['label' => 'KB', 'email' => 'adminunit.kb@tarunabakti.sch.id'],
            'TK' => ['label' => 'TK', 'email' => 'adminunit.tk@tarunabakti.sch.id'],
            'SD' => ['label' => 'SD', 'email' => 'adminunit.sd@tarunabakti.sch.id'],
            'SMP' => ['label' => 'SMP', 'email' => 'adminunit.smp@tarunabakti.sch.id'],
            'SMA' => ['label' => 'SMA', 'email' => 'adminunit.sma@tarunabakti.sch.id'],
            'TBU' => ['label' => 'PMB TBU', 'email' => 'adminunit.tbu@tbu.ac.id'],
        ];

        foreach ($staff as $code => $identity) {
            $unit = Unit::query()->where('code', $code)->first();

            if (! $unit) {
                continue;
            }

            $adminUnit = User::firstOrCreate(
                ['email' => $identity['email']],
                [
                    'name' => 'Admin Unit '.$identity['label'],
                    'password' => Hash::make('password123'),
                    'phone' => '081234567890',
                    'role' => 'admin_unit',
                    'unit_id' => $unit->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );

            // Dedicated development accounts must always point to their unit
            // and carry the Admin Unit role. Password is intentionally not reset
            // for an existing account.
            $adminUnit->forceFill([
                'role' => 'admin_unit',
                'unit_id' => $unit->id,
                'is_active' => true,
            ])->save();

            $adminUnit->syncRoles(['admin_unit']);
        }
    }
}
