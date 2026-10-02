<?php

namespace Database\Seeders\Training;

use Database\Seeders\Training\Certification\AdministratorCertificationSeeder;
use Database\Seeders\Training\Certification\AdmissionOperatorCertificationSeeder;
use Database\Seeders\Training\Certification\UnitAdministratorCertificationSeeder;
use Database\Seeders\Training\Practical\AdministratorPracticalSeeder;
use Database\Seeders\Training\Practical\AdmissionOperatorPracticalSeeder;
use Database\Seeders\Training\Practical\UnitAdministratorPracticalSeeder;
use Database\Seeders\Training\Programs\AdministratorTrainingSeeder;
use Database\Seeders\Training\Programs\AdmissionOperatorTrainingSeeder;
use Database\Seeders\Training\Programs\UnitAdministratorTrainingSeeder;
use Illuminate\Database\Seeder;

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdministratorTrainingSeeder::class,
            UnitAdministratorTrainingSeeder::class,
            AdmissionOperatorTrainingSeeder::class,

            AdministratorCertificationSeeder::class,
            UnitAdministratorCertificationSeeder::class,
            AdmissionOperatorCertificationSeeder::class,

            AdministratorPracticalSeeder::class,
            UnitAdministratorPracticalSeeder::class,
            AdmissionOperatorPracticalSeeder::class,
        ]);
    }
}
