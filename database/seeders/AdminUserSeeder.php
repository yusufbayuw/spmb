<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\Support\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    use GuardsDemoEnvironment;

    public function run(): void
    {
        if ($this->shouldSkipDemoData()) {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Administrator Demo',
                'username' => 'admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
        $admin->syncRoles(['super_admin']);

        Unit::query()
            ->forOperationalMode()
            ->orderBy('id')
            ->each(function (Unit $unit): void {
                $suffix = mb_strtolower($unit->code);
                $tu = User::firstOrCreate(
                    ['email' => 'tu.'.$suffix.'@example.test'],
                    [
                        'name' => 'TU '.$unit->name,
                        'username' => 'tu.'.$suffix,
                        'password' => Hash::make('password123'),
                        'role' => 'tu',
                        'unit_id' => $unit->id,
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ],
                );

                $tu->syncRoles(['tu']);
            });
    }
}
