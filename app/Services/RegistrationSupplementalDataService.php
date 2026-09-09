<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\UnitConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegistrationSupplementalDataService
{
    public function academicScoresFormState(Registration $registration): array
    {
        $state = [];

        foreach ($registration->academicScores as $score) {
            data_set(
                $state,
                $score->grade_key.'.'.$score->subject_key.'.'.$score->assessment_key,
                (float) $score->score,
            );
        }

        return $state;
    }

    public function achievementsFormState(Registration $registration): array
    {
        return $registration->achievements
            ->map(fn ($achievement): array => [
                'title' => $achievement->title,
                'level' => $achievement->level,
                'year' => $achievement->year,
                'organizer' => $achievement->organizer,
                'description' => $achievement->description,
            ])
            ->values()
            ->all();
    }

    public function validateAcademicScores(UnitConfiguration $configuration, ?string $pathwayUuid, array $input): array
    {
        if (! $this->featureApplies($configuration->academic_scores_enabled, $configuration->academic_score_settings, $pathwayUuid)) {
            return [];
        }

        $settings = $configuration->academic_score_settings ?? [];
        $min = (float) ($settings['min_score'] ?? 0);
        $max = (float) ($settings['max_score'] ?? 100);
        $required = (bool) ($settings['required'] ?? false);
        $rows = [];
        $errors = [];

        foreach ($settings['grades'] ?? [] as $grade) {
            foreach ($settings['subjects'] ?? [] as $subject) {
                foreach ($settings['assessments'] ?? [] as $assessment) {
                    $path = ($grade['key'] ?? '').'.'.($subject['key'] ?? '').'.'.($assessment['key'] ?? '');
                    $value = data_get($input, $path);

                    if (($value === null || $value === '') && ! $required) {
                        continue;
                    }

                    if ($value === null || $value === '') {
                        $errors['academic_scores.'.$path] = 'Nilai wajib diisi.';
                        continue;
                    }

                    if (! is_numeric($value) || (float) $value < $min || (float) $value > $max) {
                        $errors['academic_scores.'.$path] = "Nilai harus berada pada rentang {$min}–{$max}.";
                        continue;
                    }

                    $rows[] = [
                        'grade_key' => $grade['key'],
                        'subject_key' => $subject['key'],
                        'assessment_key' => $assessment['key'],
                        'score' => (float) $value,
                    ];
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    public function validateAchievements(UnitConfiguration $configuration, ?string $pathwayUuid, array $input): array
    {
        if (! $this->featureApplies($configuration->achievements_enabled, $configuration->achievement_settings, $pathwayUuid)) {
            return [];
        }

        $settings = $configuration->achievement_settings ?? [];
        $maxEntries = (int) ($settings['max_entries'] ?? 3);
        $levels = array_values($settings['levels'] ?? []);
        $required = (bool) ($settings['required'] ?? false);

        if ($required && count($input) === 0) {
            throw ValidationException::withMessages(['achievements' => 'Minimal satu prestasi wajib diisi.']);
        }

        if (count($input) > $maxEntries) {
            throw ValidationException::withMessages(['achievements' => "Maksimal {$maxEntries} prestasi dapat diisi."]);
        }

        $validated = validator(['items' => $input], [
            'items' => ['array', 'max:'.$maxEntries],
            'items.*.title' => ['required', 'string', 'max:200'],
            'items.*.level' => ['required', 'string', Rule::in($levels)],
            'items.*.year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'items.*.organizer' => ['nullable', 'string', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return $validated['items'] ?? [];
    }

    public function sync(Registration $registration, array $academicScores, array $achievements): void
    {
        DB::transaction(function () use ($registration, $academicScores, $achievements): void {
            $registration->academicScores()->delete();
            if ($academicScores) {
                $registration->academicScores()->createMany($academicScores);
            }

            $registration->achievements()->delete();
            if ($achievements) {
                $registration->achievements()->createMany($achievements);
            }
        });
    }

    public function featureApplies(bool $enabled, ?array $settings, ?string $pathwayUuid): bool
    {
        if (! $enabled) {
            return false;
        }

        $pathways = array_values($settings['pathway_uuids'] ?? []);

        return $pathways === [] || ($pathwayUuid && in_array($pathwayUuid, $pathways, true));
    }
}
