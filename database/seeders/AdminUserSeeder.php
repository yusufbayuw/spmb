<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@tarunabakti.sch.id'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password123'),
                'phone' => '081234567890',
                'role' => 'admin',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
        $admin->syncRoles(['super_admin']);

        $staff = [
            'DC' => ['label' => 'Daycare', 'email' => 'tu.dc@tarunabakti.sch.id'],
            'KB' => ['label' => 'KB', 'email' => 'tu.kb@tarunabakti.sch.id'],
            'TK' => ['label' => 'TK', 'email' => 'tu.tk@tarunabakti.sch.id'],
            'SD' => ['label' => 'SD', 'email' => 'tu.sd@tarunabakti.sch.id'],
            'SMP' => ['label' => 'SMP', 'email' => 'tu.smp@tarunabakti.sch.id'],
            'SMA' => ['label' => 'SMA', 'email' => 'tu.sma@tarunabakti.sch.id'],
            'TBU' => ['label' => 'PMB TBU', 'email' => 'tu.tbu@tbu.ac.id'],
        ];

        foreach ($staff as $code => $identity) {
            $unit = Unit::query()->where('code', $code)->first();

            if (! $unit) {
                continue;
            }

            $tu = User::updateOrCreate(
                ['email' => $identity['email']],
                [
                    'name' => 'TU '.$identity['label'],
                    'password' => Hash::make('password123'),
                    'phone' => '081234567890',
                    'role' => 'tu',
                    'unit_id' => $unit->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
            $tu->syncRoles(['tu']);
        }
    }
}
