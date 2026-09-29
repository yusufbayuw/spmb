<?php

namespace Database\Seeders;

use Database\Seeders\Support\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    use GuardsDemoEnvironment;

    public function run(): void
    {
        if ($this->shouldSkipDemoData()) {
            return;
        }

        $this->call([
            UnitSeeder::class,
            StudyProgramSeeder::class,
            RegistrationPathwaySeeder::class,
            PublicContentSeeder::class,
            UnitRegistrationConfigurationSeeder::class,
            RegistrationOpeningSeeder::class,
            AdmissionQuotaSeeder::class,
            AdminUserSeeder::class,
            AdminUnitUserSeeder::class,
            TestSessionSeeder::class,
            PaymentReceiptSeeder::class,
        ]);
    }
}
