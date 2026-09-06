<?php

namespace Database\Seeders;

use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Services\UnitConfigurationService;
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

            RegistrationOpening::firstOrCreate(
                [
                    'unit_id' => $unit->id,
                    'study_program_id' => null,
                    'academic_year' => '2026/2027',
                    'wave' => 'Gelombang 1',
                ],
                [
                    'registration_fee' => app(UnitConfigurationService::class)->current($unit->id)?->payment_enabled === false ? 0 : (in_array($code, ['SD', 'SMP'], true) ? 385000 : 0),
                    'description' => 'Contoh pembukaan SPMB '.$unit->name.'. Periksa dan lengkapi nominal serta periode operasional sebelum dipublikasikan.',
                    'status' => 'draft',
                    'opened_at' => now()->addWeek()->startOfDay(),
                    'closed_at' => now()->addMonths(2)->endOfDay(),
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
                RegistrationOpening::firstOrCreate(
                    [
                        'unit_id' => $tbu->id,
                        'study_program_id' => $program->id,
                        'academic_year' => '2026/2027',
                        'wave' => 'Gelombang 1',
                    ],
                    [
                        'registration_fee' => app(UnitConfigurationService::class)->current($tbu->id)?->payment_enabled === false ? 0 : 350000,
                        'description' => 'PMB Taruna Bakti University '.$program->label().' Tahun Akademik 2026/2027.',
                        'status' => 'open',
                        'opened_at' => now()->subDay(),
                        'closed_at' => now()->addMonths(2)->endOfDay(),
                    ],
                );
            });
    }
}
