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
        $admin = User::updateOrCreate(['email' => 'admin@tarunabakti.sch.id'], ['name' => 'Administrator','password' => Hash::make('password123'),'phone' => '081234567890','role' => 'admin','email_verified_at' => now(),'is_active' => true]);
        $admin->syncRoles(['super_admin']);

        foreach (['DC' => 'Daycare','KB' => 'KB','TK' => 'TK','SD' => 'SD','SMP' => 'SMP','SMA' => 'SMA'] as $code => $label) {
            if ($unit = Unit::where('code', $code)->first()) {
                $tu = User::updateOrCreate(['email' => 'tu.'.strtolower($code).'@tarunabakti.sch.id'], ['name' => 'TU '.$label,'password' => Hash::make('password123'),'phone' => '081234567890','role' => 'tu','unit_id' => $unit->id,'email_verified_at' => now(),'is_active' => true]);
                $tu->syncRoles(['tu']);
            }
        }
    }
}
