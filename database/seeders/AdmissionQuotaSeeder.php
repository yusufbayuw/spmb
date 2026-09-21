<?php

namespace Database\Seeders;

use App\Models\AdmissionQuota;
use App\Models\RegistrationOpening;
use Illuminate\Database\Seeder;

class AdmissionQuotaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        RegistrationOpening::query()->forOperationalMode()->with('unit')->each(function (RegistrationOpening $opening): void {
            AdmissionQuota::firstOrCreate(
                ['registration_opening_id' => $opening->id, 'registration_pathway_id' => null],
                [
                    'capacity' => $opening->unit?->isHigherEducation() ? 80 : 200,
                    'offer_expires_in_hours' => 72,
                    're_registration_due_in_days' => 14,
                    'is_active' => true,
                ],
            );
        });
    }
}
