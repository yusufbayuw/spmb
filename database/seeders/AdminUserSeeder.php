<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::updateOrCreate(
            ['email' => 'admin@tarunabakti.sch.id'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password123'),
                'phone' => '081234567890',
                'role' => 'admin',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // TU untuk masing-masing unit
        $tuUsers = [
            [
                'name' => 'TU Daycare',
                'email' => 'tu.daycare@tarunabakti.sch.id',
                'unit_code' => 'DC',
            ],
            [
                'name' => 'TU KB',
                'email' => 'tu.kb@tarunabakti.sch.id',
                'unit_code' => 'KB',
            ],
            [
                'name' => 'TU TK',
                'email' => 'tu.tk@tarunabakti.sch.id',
                'unit_code' => 'TK',
            ],
            [
                'name' => 'TU SD',
                'email' => 'tu.sd@tarunabakti.sch.id',
                'unit_code' => 'SD',
            ],
            [
                'name' => 'TU SMP',
                'email' => 'tu.smp@tarunabakti.sch.id',
                'unit_code' => 'SMP',
            ],
            [
                'name' => 'TU SMA',
                'email' => 'tu.sma@tarunabakti.sch.id',
                'unit_code' => 'SMA',
            ],
        ];

        foreach ($tuUsers as $tu) {
            $unit = Unit::where('code', $tu['unit_code'])->first();
            
            if ($unit) {
                User::updateOrCreate(
                    ['email' => $tu['email']],
                    [
                        'name' => $tu['name'],
                        'password' => Hash::make('password123'),
                        'phone' => '081234567890',
                        'role' => 'tu',
                        'unit_id' => $unit->id,
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}