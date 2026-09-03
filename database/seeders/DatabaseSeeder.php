<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UnitSeeder::class,
            StudyProgramSeeder::class,
            RegistrationOpeningSeeder::class,
            ShieldSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
