<?php

namespace Database\Seeders;

use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class RegistrationOpeningSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['DC', 'KB', 'TK', 'SD', 'SMP', 'SMA'] as $code) {
            $unit = Unit::query()->where('code', $code)->first();

            if (! $unit) {
                continue;
            }

            RegistrationOpening::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'study_program_id' => null,
                    'academic_year' => '2026/2027',
                    'wave' => 'Gelombang 1',
                    'pathway' => 'Reguler',
                ],
                [
                    'registration_fee' => in_array($code, ['SD', 'SMP'], true) ? 385000 : 0,
                    'description' => 'Contoh pembukaan SPMB '.$unit->name.'. Periksa dan lengkapi nominal serta periode operasional sebelum dipublikasikan.',
                    'status' => 'draft',
                ],
            );
        }

        $tbu = Unit::query()->where('code', 'TBU')->first();

        if (! $tbu) {
            return;
        }

        StudyProgram::query()
            ->where('unit_id', $tbu->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->each(function (StudyProgram $program) use ($tbu): void {
                RegistrationOpening::updateOrCreate(
                    [
                        'unit_id' => $tbu->id,
                        'study_program_id' => $program->id,
                        'academic_year' => '2026/2027',
                        'wave' => 'Gelombang 1',
                        'pathway' => 'Reguler',
                    ],
                    [
                        'registration_fee' => 350000,
                        'description' => 'PMB Taruna Bakti University '.$program->label().' Tahun Akademik 2026/2027.',
                        'status' => 'open',
                        'opened_at' => now(),
                    ],
                );
            });
    }
}
