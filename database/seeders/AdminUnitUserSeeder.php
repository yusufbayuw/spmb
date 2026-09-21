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
            'DC' => [
                'label' => 'Daycare',
                'username' => 'admin.daycare',
                'email' => 'admin.daycare@tarunabakti.sch.id',
                'legacy_email' => 'adminunit.dc@tarunabakti.sch.id',
            ],
            'KB' => [
                'label' => 'KB',
                'username' => 'admin.kb',
                'email' => 'admin.kb@tarunabakti.sch.id',
                'legacy_email' => 'adminunit.kb@tarunabakti.sch.id',
            ],
            'TK' => [
                'label' => 'TK',
                'username' => 'admin.tk',
                'email' => 'admin.tk@tarunabakti.sch.id',
                'legacy_email' => 'adminunit.tk@tarunabakti.sch.id',
            ],
            'SD' => [
                'label' => 'SD',
                'username' => 'admin.sd',
                'email' => 'admin.sd@tarunabakti.sch.id',
                'legacy_email' => 'adminunit.sd@tarunabakti.sch.id',
            ],
            'SMP' => [
                'label' => 'SMP',
                'username' => 'admin.smp',
                'email' => 'admin.smp@tarunabakti.sch.id',
                'legacy_email' => 'adminunit.smp@tarunabakti.sch.id',
            ],
            'SMA' => [
                'label' => 'SMA',
                'username' => 'admin.sma',
                'email' => 'admin.sma@tarunabakti.sch.id',
                'legacy_email' => 'adminunit.sma@tarunabakti.sch.id',
            ],
            'TBU' => [
                'label' => 'TBU',
                'username' => 'admin.tbu',
                'email' => 'admin.tbu@tbu.ac.id',
                'legacy_email' => 'adminunit.tbu@tbu.ac.id',
            ],
        ];

        foreach ($staff as $code => $identity) {
            $unit = Unit::query()->forOperationalMode()->where('code', $code)->first();

            if (! $unit) {
                continue;
            }

            // Reuse the previous development account when upgrading an existing
            // database so we do not create duplicate Admin Unit users or reset
            // a password that a developer has already changed.
            $adminUnit = User::query()
                ->where('email', $identity['email'])
                ->orWhere('username', $identity['username'])
                ->orWhere('email', $identity['legacy_email'])
                ->first();

            if (! $adminUnit) {
                $adminUnit = User::create([
                    'name' => 'Admin '.$identity['label'],
                    'username' => $identity['username'],
                    'email' => $identity['email'],
                    'password' => Hash::make('password123'),
                    'phone' => '081234567890',
                    'role' => 'admin_unit',
                    'unit_id' => $unit->id,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
            } else {
                $adminUnit->forceFill([
                    'name' => 'Admin '.$identity['label'],
                    'username' => $identity['username'],
                    'email' => $identity['email'],
                    'role' => 'admin_unit',
                    'unit_id' => $unit->id,
                    'is_active' => true,
                ])->save();
            }

            $adminUnit->syncRoles(['admin_unit']);
        }
    }
}
