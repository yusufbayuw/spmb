<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Regency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<District> */
class DistrictFactory extends Factory
{
    public function definition(): array
    {
        $regency = Regency::factory()->create();

        return [
            'code' => $regency->code.'.'.fake()->unique()->numerify('##'),
            'regency_code' => $regency->code,
            'name' => 'Kecamatan '.fake()->citySuffix(),
        ];
    }
}
