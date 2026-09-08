<?php

namespace Database\Factories;

use App\Models\Province;
use App\Models\Regency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Regency> */
class RegencyFactory extends Factory
{
    public function definition(): array
    {
        $province = Province::factory()->create();

        return [
            'code' => $province->code.'.'.fake()->unique()->numerify('##'),
            'province_code' => $province->code,
            'name' => 'Kota '.fake()->city(),
        ];
    }
}
