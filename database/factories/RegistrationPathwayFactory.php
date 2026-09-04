<?php

namespace Database\Factories;

use App\Models\RegistrationPathway;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationPathway>
 */
class RegistrationPathwayFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'unit_id' => fn (): int => Unit::query()->firstOrCreate(
                ['code' => 'TEST'],
                [
                    'name' => 'Unit Pengujian',
                    'institution_type' => 'school',
                    'is_active' => true,
                ],
            )->id,
            'name' => fake()->randomElement(['Reguler', 'Prestasi', 'Afirmasi', 'Mandiri']).' '.fake()->unique()->numberBetween(1, 999),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
