<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Support\SpmbOperationalMode;
use Database\Seeders\Support\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    use GuardsDemoEnvironment;

    public function run(): void
    {
        if ($this->shouldSkipDemoData()) {
            return;
        }

        $units = [
            [
                'name' => 'Daycare',
                'code' => 'DC',
                'institution_type' => 'early_childhood',
                'description' => 'Layanan pendidikan dan pengasuhan anak usia dini.',
                'is_active' => true,
            ],
            [
                'name' => 'Kelompok Bermain',
                'code' => 'KB',
                'institution_type' => 'early_childhood',
                'description' => 'Layanan kelompok bermain untuk pendidikan anak usia dini.',
                'is_active' => true,
            ],
            [
                'name' => 'Taman Kanak-Kanak',
                'code' => 'TK',
                'institution_type' => 'early_childhood',
                'description' => 'Layanan pendidikan taman kanak-kanak.',
                'is_active' => true,
            ],
            [
                'name' => 'Sekolah Dasar',
                'code' => 'SD',
                'institution_type' => 'school',
                'description' => 'Layanan pendidikan sekolah dasar.',
                'is_active' => true,
            ],
            [
                'name' => 'Sekolah Menengah Pertama',
                'code' => 'SMP',
                'institution_type' => 'school',
                'description' => 'Layanan pendidikan sekolah menengah pertama.',
                'is_active' => true,
            ],
            [
                'name' => 'Sekolah Menengah Atas',
                'code' => 'SMA',
                'institution_type' => 'school',
                'description' => 'Layanan pendidikan sekolah menengah atas.',
                'is_active' => true,
            ],
            [
                'name' => 'Perguruan Tinggi',
                'code' => 'PT',
                'institution_type' => 'university',
                'description' => 'Layanan penerimaan mahasiswa program diploma dan sarjana.',
                'is_active' => true,
            ],
        ];

        foreach ($units as $unit) {
            if (! SpmbOperationalMode::allowsInstitutionType($unit['institution_type'])) {
                continue;
            }

            Unit::firstOrCreate(['code' => $unit['code']], $unit);
        }
    }
}
