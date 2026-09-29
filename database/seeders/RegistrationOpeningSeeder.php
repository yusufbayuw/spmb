<?php

namespace Database\Seeders;

use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Services\UnitConfigurationService;
use App\Support\SpmbOperationalMode;
use Database\Seeders\Support\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;

class RegistrationOpeningSeeder extends Seeder
{
    use GuardsDemoEnvironment;

    public function run(): void
    {
        if ($this->shouldSkipDemoData()) {
            return;
        }

        foreach (SpmbOperationalMode::allowsK12() ? ['DC', 'KB', 'TK', 'SD', 'SMP', 'SMA'] : [] as $code) {
            $unit = Unit::query()->forOperationalMode()->where('code', $code)->first();

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

        if (! SpmbOperationalMode::allowsHigherEducation()) {
            return;
        }

        $university = Unit::query()
            ->forOperationalMode()
            ->where('institution_type', 'university')
            ->orderBy('id')
            ->first();

        if (! $university) {
            return;
        }

        StudyProgram::query()
            ->where('unit_id', $university->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->each(function (StudyProgram $program) use ($university): void {
                RegistrationOpening::firstOrCreate(
                    [
                        'unit_id' => $university->id,
                        'study_program_id' => $program->id,
                        'academic_year' => '2026/2027',
                        'wave' => 'Gelombang 1',
                    ],
                    [
                        'registration_fee' => app(UnitConfigurationService::class)->current($university->id)?->payment_enabled === false ? 0 : 350000,
                        'description' => 'Contoh PMB '.$university->name.' '.$program->label().' Tahun Akademik 2026/2027.',
                        'status' => 'open',
                        'opened_at' => now()->subDay(),
                        'closed_at' => now()->addMonths(2)->endOfDay(),
                    ],
                );
            });
    }
}
