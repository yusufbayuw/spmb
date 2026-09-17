<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EducationLevelSeeder::class,
            UnitSeeder::class,
            StudyProgramSeeder::class,
            RegistrationPathwaySeeder::class,
            UnitRegistrationConfigurationSeeder::class,
            RegistrationOpeningSeeder::class,
            AdmissionQuotaSeeder::class,
            ShieldSeeder::class,
            AdminUserSeeder::class,
            AdminUnitUserSeeder::class,
            TestSessionSeeder::class,
            PaymentReceiptSeeder::class,
        ]);
    }
}
