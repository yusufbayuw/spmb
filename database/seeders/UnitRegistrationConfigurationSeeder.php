<?php

namespace Database\Seeders;

use App\Models\Registration;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Services\UnitConfigurationService;
use Illuminate\Database\Seeder;

class UnitRegistrationConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(UnitConfigurationService::class);
        Unit::query()->forOperationalMode()->each(function (Unit $unit) use ($service): void {
            if ($service->current($unit->id) || Registration::where('unit_id', $unit->id)->exists()) {
                $service->initialize($unit);

                return;
            }

            // An unpublished draft belongs to TU and must not be published by seeding.
            if (UnitConfiguration::where('unit_id', $unit->id)->exists()) {
                return;
            }

            $defaults = $service->defaults($unit);
            $defaults['tests_enabled'] = collect($defaults['test_definitions'])->contains('is_required', true);
            UnitConfiguration::create($defaults + [
                'unit_id' => $unit->id,
                'version' => 1,
                'status' => 'published',
                'legacy' => false,
                'published_at' => now(),
            ]);
        });
    }
}
