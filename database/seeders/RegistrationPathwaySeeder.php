<?php

namespace Database\Seeders;

use App\Models\RegistrationPathway;
use App\Models\Unit;
use Database\Seeders\Support\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;

class RegistrationPathwaySeeder extends Seeder
{
    use GuardsDemoEnvironment;

    public function run(): void
    {
        if ($this->shouldSkipDemoData()) {
            return;
        }

        Unit::query()->forOperationalMode()->each(function (Unit $unit): void {
            RegistrationPathway::query()->firstOrCreate(
                [
                    'unit_id' => $unit->id,
                    'name' => 'Reguler',
                ],
                [
                    'description' => 'Jalur pendaftaran reguler '.$unit->name.'.',
                    'is_active' => true,
                    'archived_at' => null,
                ],
            );
        });
    }
}
