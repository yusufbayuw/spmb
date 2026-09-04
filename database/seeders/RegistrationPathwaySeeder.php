<?php

namespace Database\Seeders;

use App\Models\RegistrationPathway;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class RegistrationPathwaySeeder extends Seeder
{
    public function run(): void
    {
        Unit::query()->each(function (Unit $unit): void {
            RegistrationPathway::query()->updateOrCreate(
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
