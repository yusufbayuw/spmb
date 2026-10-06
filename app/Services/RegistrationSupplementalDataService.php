<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\RegistrationAchievement;
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
            ->map(fn (RegistrationAchievement $achievement): array => [
                'uuid' => $achievement->uuid,
                'title' => $achievement->title,
                'level' => $achievement->level,
                'year' => $achievement->year,
                'organizer' => $achievement->organizer,
                'description' => $achievement->description,
                // Private certificate paths are deliberately not hydrated into FileUpload.
                // validateAchievements() preserves the existing file by achievement UUID.
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
                foreach ($this->assessmentsForGrade($settings, (string) ($grade['key'] ?? '')) as $assessment) {
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

    public function validateAchievements(
        UnitConfiguration $configuration,
        ?string $pathwayUuid,
        array $input,
        ?Registration $registration = null,
    ): array {
        if (! $this->featureApplies($configuration->achievements_enabled, $configuration->achievement_settings, $pathwayUuid)) {
            return [];
        }

        $settings = $configuration->achievement_settings ?? [];
        $maxEntries = (int) ($settings['max_entries'] ?? 3);
        $levels = array_values($settings['levels'] ?? []);
        $required = (bool) ($settings['required'] ?? false);
        $certificateMode = $configuration->achievementCertificateMode();

        if ($required && count($input) === 0) {
            throw ValidationException::withMessages(['achievements' => 'Minimal satu prestasi wajib diisi.']);
        }

        if (count($input) > $maxEntries) {
            throw ValidationException::withMessages(['achievements' => "Maksimal {$maxEntries} prestasi dapat diisi."]);
        }

        $validated = validator(['items' => $input], [
            'items' => ['array', 'max:'.$maxEntries],
            'items.*.uuid' => ['nullable', 'uuid', 'distinct'],
            'items.*.title' => ['required', 'string', 'max:200'],
            'items.*.level' => ['required', 'string', Rule::in($levels)],
            'items.*.year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'items.*.organizer' => ['nullable', 'string', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.certificate_path' => ['nullable', 'string', 'max:1000'],
            'items.*.certificate_original_name' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $existingByUuid = $registration
            ? $registration->achievements()->get()->keyBy('uuid')
            : collect();

        $userId = $registration?->user_id ?: auth()->id();
        $expectedPrefix = $userId ? 'pre-registration/'.$userId.'/achievements/' : null;
        $rows = [];

        foreach ($validated['items'] ?? [] as $index => $item) {
            $uuid = filled($item['uuid'] ?? null) ? (string) $item['uuid'] : null;
            $existing = $uuid ? $existingByUuid->get($uuid) : null;

            if ($uuid && ! $existing) {
                throw ValidationException::withMessages([
                    'achievements.'.$index.'.uuid' => 'Data prestasi tidak valid atau bukan milik pendaftaran ini.',
                ]);
            }

            $newPath = filled($item['certificate_path'] ?? null)
                ? (string) $item['certificate_path']
                : null;

            if ($newPath && $certificateMode === UnitConfiguration::ACHIEVEMENT_CERTIFICATE_MODE_NONE) {
                throw ValidationException::withMessages([
                    'achievements.'.$index.'.certificate_path' => 'Upload sertifikat tidak digunakan pada konfigurasi prestasi ini.',
                ]);
            }

            if ($newPath) {
                if (! $expectedPrefix || ! str_starts_with($newPath, $expectedPrefix)) {
                    throw ValidationException::withMessages([
                        'achievements.'.$index.'.certificate_path' => 'Lokasi file sertifikat tidak valid.',
                    ]);
                }

                app(ApplicantUploadSecurity::class)->inspect($newPath, ['pdf', 'jpg', 'png']);
            }

            $certificatePath = $newPath ?: $existing?->certificate_path;
            $certificateOriginalName = $newPath
                ? basename((string) ($item['certificate_original_name'] ?? $newPath))
                : $existing?->certificate_original_name;

            if ($certificateMode === UnitConfiguration::ACHIEVEMENT_CERTIFICATE_MODE_REQUIRED
                && blank($certificatePath)) {
                throw ValidationException::withMessages([
                    'achievements.'.$index.'.certificate_path' => 'Sertifikat/bukti prestasi wajib diunggah.',
                ]);
            }

            $rows[] = [
                'uuid' => $uuid,
                'title' => $item['title'],
                'level' => $item['level'],
                'year' => $item['year'] ?? null,
                'organizer' => $item['organizer'] ?? null,
                'description' => $item['description'] ?? null,
                'certificate_path' => $certificatePath,
                'certificate_original_name' => $certificateOriginalName,
            ];
        }

        return $rows;
    }

    public function sync(Registration $registration, array $academicScores, array $achievements): void
    {
        $obsoleteCertificatePaths = [];

        DB::transaction(function () use ($registration, $academicScores, $achievements, &$obsoleteCertificatePaths): void {
            $registration->academicScores()->delete();
            if ($academicScores) {
                $registration->academicScores()->createMany($academicScores);
            }

            $existing = $registration->achievements()->lockForUpdate()->get()->keyBy('uuid');
            $keptIds = [];

            foreach ($achievements as $achievementData) {
                $uuid = $achievementData['uuid'] ?? null;
                unset($achievementData['uuid']);

                /** @var RegistrationAchievement|null $achievement */
                $achievement = $uuid ? $existing->get($uuid) : null;

                if ($achievement) {
                    $oldPath = $achievement->certificate_path;
                    $achievement->update($achievementData);
                    $keptIds[] = $achievement->id;

                    if ($oldPath && $oldPath !== $achievement->certificate_path) {
                        $obsoleteCertificatePaths[] = $oldPath;
                    }

                    continue;
                }

                $created = $registration->achievements()->create($achievementData);
                $keptIds[] = $created->id;
            }

            $removed = $registration->achievements()
                ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
                ->get();

            foreach ($removed as $achievement) {
                if ($achievement->certificate_path) {
                    $obsoleteCertificatePaths[] = $achievement->certificate_path;
                }
            }

            if ($keptIds === []) {
                $registration->achievements()->delete();
            } else {
                $registration->achievements()->whereNotIn('id', $keptIds)->delete();
            }
        });

        foreach (array_unique(array_filter($obsoleteCertificatePaths)) as $path) {
            app(ApplicantFileStorage::class)->delete($path);
        }
    }

    /**
     * Return only score components that apply to a grade.
     *
     * Legacy assessments without grade_keys still apply to every grade.
     *
     * @return list<array<string, mixed>>
     */
    public function assessmentsForGrade(array $settings, string $gradeKey): array
    {
        return collect($settings['assessments'] ?? [])
            ->filter(function (array $assessment) use ($gradeKey): bool {
                $gradeKeys = array_values(array_filter($assessment['grade_keys'] ?? []));

                return $gradeKeys === [] || in_array($gradeKey, $gradeKeys, true);
            })
            ->values()
            ->all();
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
