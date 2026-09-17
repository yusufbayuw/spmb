<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EducationLevel::DEFAULT_LEVELS as $level) {
            EducationLevel::firstOrCreate(
                ['code' => $level['code']],
                $level + ['is_active' => true],
            );
        }
    }
}
