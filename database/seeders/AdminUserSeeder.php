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
        $admin = User::firstOrCreate(
            ['email' => 'admin@tarunabakti.sch.id'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('password123'),
                'phone' => '081234567890',
                'role' => 'admin',
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        if ($admin->wasRecentlyCreated) {
            $admin->syncRoles(['super_admin']);
        } elseif ($admin->username !== 'admin') {
            // Username is the only canonical field introduced by this upgrade.
            // Preserve password, active state, name, and other manual changes.
            $admin->forceFill(['username' => 'admin'])->save();
        }

        $staff = [
            'DC' => ['label' => 'Daycare', 'username' => 'tu.daycare', 'email' => 'tu.dc@tarunabakti.sch.id'],
            'KB' => ['label' => 'KB', 'username' => 'tu.kb', 'email' => 'tu.kb@tarunabakti.sch.id'],
            'TK' => ['label' => 'TK', 'username' => 'tu.tk', 'email' => 'tu.tk@tarunabakti.sch.id'],
            'SD' => ['label' => 'SD', 'username' => 'tu.sd', 'email' => 'tu.sd@tarunabakti.sch.id'],
            'SMP' => ['label' => 'SMP', 'username' => 'tu.smp', 'email' => 'tu.smp@tarunabakti.sch.id'],
            'SMA' => ['label' => 'SMA', 'username' => 'tu.sma', 'email' => 'tu.sma@tarunabakti.sch.id'],
            'TBU' => ['label' => 'PMB TBU', 'username' => 'tu.tbu', 'email' => 'tu.tbu@tbu.ac.id'],
        ];

        foreach ($staff as $code => $identity) {
            $unit = Unit::query()->forOperationalMode()->where('code', $code)->first();

            if (! $unit) {
                continue;
            }

            $tu = User::firstOrCreate(
                ['email' => $identity['email']],
                [
                    'name' => 'TU '.$identity['label'],
                    'username' => $identity['username'],
                    'password' => Hash::make('password123'),
                    'phone' => '081234567890',
                    'role' => 'tu',
                    'unit_id' => $unit->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );

            if ($tu->wasRecentlyCreated) {
                $tu->syncRoles(['tu']);
            } elseif ($tu->username !== $identity['username']) {
                // Do not reset operational changes on an existing staff account.
                $tu->forceFill(['username' => $identity['username']])->save();
            }
        }
    }
}
