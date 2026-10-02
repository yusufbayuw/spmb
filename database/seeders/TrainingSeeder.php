<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Public entry point for the complete SPMB training curriculum.
 *
 * Run with:
 * php artisan db:seed --class=TrainingSeeder
 */
class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(\Database\Seeders\Training\TrainingSeeder::class);
    }
}
