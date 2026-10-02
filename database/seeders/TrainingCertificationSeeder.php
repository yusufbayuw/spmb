<?php

namespace Database\Seeders;

use Database\Seeders\Training\TrainingSeeder;
use Illuminate\Database\Seeder;

/**
 * Backward-compatible entry point.
 *
 * Existing deployments and tests may still call TrainingCertificationSeeder.
 * The complete curriculum now lives under Database\Seeders\Training.
 */
class TrainingCertificationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TrainingSeeder::class);
    }
}
