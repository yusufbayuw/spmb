<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\Support\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUnitUserSeeder extends Seeder
{
    use GuardsDemoEnvironment;

    public function run(): void
    {
        if ($this->shouldSkipDemoData()) {
            return;
        }

        $this->call(AdminUnitRoleSeeder::class);

        Unit::query()
            ->forOperationalMode()
            ->orderBy('id')
            ->each(function (Unit $unit): void {
                $suffix = mb_strtolower($unit->code);
                $adminUnit = User::firstOrCreate(
                    ['email' => 'admin.'.$suffix.'@example.test'],
                    [
                        'name' => 'Admin '.$unit->name,
                        'username' => 'admin.'.$suffix,
                        'password' => Hash::make('password123'),
                        'role' => 'admin_unit',
                        'unit_id' => $unit->id,
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ],
                );

                $adminUnit->syncRoles(['admin_unit']);
            });
    }
}
