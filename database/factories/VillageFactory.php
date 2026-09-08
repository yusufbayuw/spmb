<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Village> */
class VillageFactory extends Factory
{
    public function definition(): array
    {
        $district = District::factory()->create();

        return [
            'code' => $district->code.'.'.fake()->unique()->numerify('####'),
            'district_code' => $district->code,
            'name' => 'Kelurahan '.fake()->streetName(),
        ];
    }
}
