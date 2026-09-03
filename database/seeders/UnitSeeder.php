<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            [
                'name' => 'Daycare',
                'code' => 'DC',
                'institution_type' => 'early_childhood',
                'description' => 'Daycare Taruna Bakti untuk layanan pendidikan dan pengasuhan anak usia dini.',
                'is_active' => true,
            ],
            [
                'name' => 'Kelompok Bermain',
                'code' => 'KB',
                'institution_type' => 'early_childhood',
                'description' => 'Kelompok Bermain Taruna Bakti untuk pendidikan anak usia dini.',
                'is_active' => true,
            ],
            [
                'name' => 'Taman Kanak-Kanak',
                'code' => 'TK',
                'institution_type' => 'early_childhood',
                'description' => 'Taman Kanak-Kanak Taruna Bakti.',
                'is_active' => true,
            ],
            [
                'name' => 'Sekolah Dasar',
                'code' => 'SD',
                'institution_type' => 'school',
                'description' => 'Sekolah Dasar Taruna Bakti.',
                'is_active' => true,
            ],
            [
                'name' => 'Sekolah Menengah Pertama',
                'code' => 'SMP',
                'institution_type' => 'school',
                'description' => 'Sekolah Menengah Pertama Taruna Bakti.',
                'is_active' => true,
            ],
            [
                'name' => 'Sekolah Menengah Atas',
                'code' => 'SMA',
                'institution_type' => 'school',
                'description' => 'Sekolah Menengah Atas Taruna Bakti.',
                'is_active' => true,
            ],
            [
                'name' => 'Taruna Bakti University',
                'code' => 'TBU',
                'institution_type' => 'university',
                'description' => 'Taruna Bakti University untuk penerimaan mahasiswa program Diploma dan Sarjana.',
                'is_active' => true,
            ],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(['code' => $unit['code']], $unit);
        }
    }
}
