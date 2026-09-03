<?php

namespace Database\Seeders;

use App\Models\StudyProgram;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class StudyProgramSeeder extends Seeder
{
    public function run(): void
    {
        $tbu = Unit::query()->where('code', 'TBU')->firstOrFail();

        $programs = [
            [
                'code' => 'S1-MNJ',
                'name' => 'Manajemen',
                'degree_level' => 'S1',
                'faculty' => 'Fakultas Sosial dan Humaniora',
                'description' => 'Program sarjana yang mengembangkan kompetensi manajemen, kewirausahaan, dan bisnis digital.',
                'max_age' => 26,
                'sort_order' => 10,
            ],
            [
                'code' => 'S1-IF',
                'name' => 'Informatika',
                'degree_level' => 'S1',
                'faculty' => 'Fakultas Sains dan Teknologi',
                'description' => 'Program sarjana bidang komputasi dan teknologi informasi untuk membangun solusi digital.',
                'max_age' => 26,
                'sort_order' => 20,
            ],
            [
                'code' => 'S1-SD',
                'name' => 'Sains Data',
                'degree_level' => 'S1',
                'faculty' => 'Fakultas Sains dan Teknologi',
                'description' => 'Program sarjana yang berfokus pada analisis data, pola, tren, dan pengambilan keputusan berbasis data.',
                'max_age' => 26,
                'sort_order' => 30,
            ],
            [
                'code' => 'S1-RL',
                'name' => 'Rekayasa Logistik',
                'degree_level' => 'S1',
                'faculty' => 'Fakultas Sains dan Teknologi',
                'description' => 'Program sarjana yang mempelajari sistem logistik, rantai pasok, transportasi, dan distribusi.',
                'max_age' => 26,
                'sort_order' => 40,
            ],
            [
                'code' => 'S1-SM',
                'name' => 'Seni Musik',
                'degree_level' => 'S1',
                'faculty' => 'Fakultas Sosial dan Humaniora',
                'description' => 'Program sarjana seni musik yang mengembangkan kemampuan artistik, kreativitas, dan pemanfaatan teknologi musik.',
                'max_age' => 26,
                'sort_order' => 50,
            ],
            [
                'code' => 'D3-SEK',
                'name' => 'Sekretari',
                'degree_level' => 'D3',
                'faculty' => 'Fakultas Sosial dan Humaniora',
                'description' => 'Program diploma yang menyiapkan kompetensi profesional administrasi, komunikasi, dan pengelolaan perkantoran.',
                'max_age' => null,
                'sort_order' => 60,
            ],
            [
                'code' => 'D3-PM',
                'name' => 'Penyaji Musik',
                'degree_level' => 'D3',
                'faculty' => 'Fakultas Sosial dan Humaniora',
                'description' => 'Program diploma untuk pengembangan keterampilan pertunjukan, produksi, dan praktik profesional musik.',
                'max_age' => null,
                'sort_order' => 70,
            ],
        ];

        foreach ($programs as $program) {
            StudyProgram::updateOrCreate(
                ['unit_id' => $tbu->id, 'code' => $program['code']],
                $program + ['is_active' => true],
            );
        }
    }
}
