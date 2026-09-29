<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EducationLevelSeeder::class,
            ShieldSeeder::class,
        ]);
    }
}
