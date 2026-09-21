<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use App\Support\SpmbOperationalMode;
use Illuminate\Database\Seeder;

class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EducationLevel::DEFAULT_LEVELS as $level) {
            if (! in_array($level['category'], SpmbOperationalMode::allowedEducationCategories(), true)) {
                continue;
            }

            EducationLevel::firstOrCreate(
                ['code' => $level['code']],
                $level + ['is_active' => true],
            );
        }
    }
}
