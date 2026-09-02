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
                'description' => 'Daycare Taruna Bakti untuk usia 0-3 tahun',
            ],
            [
                'name' => 'Kelompok Bermain',
                'code' => 'KB',
                'description' => 'Kelompok Bermain untuk usia 3-4 tahun',
            ],
            [
                'name' => 'Taman Kanak-Kanak',
                'code' => 'TK',
                'description' => 'TK Taruna Bakti untuk usia 4-6 tahun',
            ],
            [
                'name' => 'Sekolah Dasar',
                'code' => 'SD',
                'description' => 'SD Taruna Bakti untuk usia 6-12 tahun',
            ],
            [
                'name' => 'Sekolah Menengah Pertama',
                'code' => 'SMP',
                'description' => 'SMP Taruna Bakti untuk usia 12-15 tahun',
            ],
            [
                'name' => 'Sekolah Menengah Atas',
                'code' => 'SMA',
                'description' => 'SMA Taruna Bakti untuk usia 15-18 tahun',
            ],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }
    }
}