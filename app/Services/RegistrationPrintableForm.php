<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\UnitConfiguration;
use DateTimeInterface;
use Illuminate\Support\Str;

class RegistrationPrintableForm
{
    /**
     * A read-only presentation of the registration's pinned configuration.
     * Never rewrite answers, scores, workflow state, or the published configuration.
     *
     * @return list<array{key: string, title: string, rows: array}>
     */
    public function sections(Registration $registration): array
    {
        $registration->loadMissing([
            'configuration', 'unit', 'opening.studyProgram', 'pathway', 'parentInfo', 'academicScores', 'achievements',
        ]);

        $configuration = $registration->configuration;
        $answers = is_array($registration->custom_answers) ? $registration->custom_answers : [];
        $sections = [
            'registration_choice' => [
                'key' => 'registration_choice', 'title' => 'Informasi Pendaftaran',
                'rows' => [
                    $this->row('Nomor Registrasi', $registration->registration_number),
                    $this->row('Unit', $registration->unit?->name),
                    $this->row('Tahun Ajaran', $registration->opening?->academic_year),
                    $this->row('Gelombang', $registration->opening?->wave),
                    $this->row('Program Studi', $registration->opening?->studyProgram?->name),
                    $this->row('Jalur Pendaftaran', $registration->pathway?->name),
                ],
            ],
            'identity' => [
                'key' => 'identity', 'title' => 'Identitas Peserta',
                'rows' => [
                    $this->row('Nama Lengkap', $registration->full_name),
                    $this->row('NIK', $registration->nik),
                    $this->row('Jenis Kelamin', $registration->gender),
                    $this->row('Tempat Lahir', $registration->birth_place),
                    $this->row('Tanggal Lahir', $registration->birth_date),
                    $this->row('Alamat', $registration->home_address),
                ],
            ],
            'parents' => ['key' => 'parents', 'title' => 'Orang Tua / Wali', 'rows' => []],
        ];

        $definitions = collect($configuration?->fields ?? [])->filter('is_array')->keyBy('key');
        $form = app(ConfiguredRegistrationForm::class);

        foreach (ConfiguredRegistrationForm::BUILTIN_FIELDS as $key) {
            if ($configuration && $form->builtinFieldState($configuration, $key)['active'] === false) {
                continue;
            }

            $definition = $definitions->get($key, []);
            $parentField = Str::startsWith($key, ['father_', 'mother_']);
            $value = $parentField
                ? $registration->parentInfo?->getAttribute($key)
                : $registration->getAttribute($key);

            // Do not print absent optional built-in fields.
            if (blank($value) && $value !== 0 && $value !== false) {
                continue;
            }

            $label = $definition['label'] ?? ConfiguredRegistrationForm::fieldLabels()[$key] ?? $key;
            $target = $parentField ? 'parents' : 'identity';
            $sections[$target]['rows'][] = $this->row($label, $value, $definition);
        }

        foreach ([
            'guardian_name' => 'Nama Wali',
            'guardian_relationship' => 'Hubungan Wali',
            'guardian_phone' => 'Telepon Wali',
            'guardian_address' => 'Alamat Wali',
        ] as $key => $label) {
            $value = $registration->parentInfo?->getAttribute($key);

            if (filled($value)) {
                $sections['parents']['rows'][] = $this->row($label, $value);
            }
        }

        $groupNames = collect($configuration?->form_groups ?? [])
            ->filter('is_array')
            ->filter(fn (array $group): bool => filled($group['key'] ?? null))
            ->mapWithKeys(fn (array $group): array => [(string) $group['key'] => (string) ($group['label'] ?? 'Informasi Tambahan')])
            ->all();

        foreach ($configuration?->fields ?? [] as $field) {
            if (! is_array($field) || ! ($field['active'] ?? false) || ! filled($field['key'] ?? null)) {
                continue;
            }

            $key = (string) $field['key'];

            if (in_array($key, ConfiguredRegistrationForm::BUILTIN_FIELDS, true)
                || ! array_key_exists($key, $answers)) {
                continue;
            }

            $groupLabel = filled($field['group'] ?? null) ? (string) $field['group'] : 'Informasi Tambahan';
            $groupKey = filled($field['group_key'] ?? null)
                ? (string) $field['group_key']
                : ($groupLabel === 'Informasi Tambahan'
                    ? 'group_additional'
                    : 'group_'.substr(sha1(mb_strtolower(trim($groupLabel))), 0, 10));
            $sectionKey = 'group:'.$groupKey;

            if (! isset($sections[$sectionKey])) {
                $sections[$sectionKey] = [
                    'key' => $sectionKey,
                    'title' => $groupNames[$groupKey] ?? $groupLabel,
                    'rows' => [],
                ];
            }

            $answer = $answers[$key];
            $sections[$sectionKey]['rows'][] = $this->row(
                (string) ($field['label'] ?? $key),
                ($field['type'] ?? '') === 'file' && filled($answer) ? 'Dokumen terlampir (tersimpan privat)' : $answer,
                $field,
            );

            if (($field['type'] ?? '') === 'boolean') {
                $details = $form->booleanDetailSettings($field, $answer);
                $detailAnswer = data_get($answers, '_details.'.$key);

                if ($details && filled($detailAnswer)) {
                    $sections[$sectionKey]['rows'][] = $this->row(
                        (string) ($details['label'] ?? 'Keterangan'),
                        $detailAnswer,
                    );
                }
            }
        }

        if ($registration->academicScores->isNotEmpty()) {
            $settings = $configuration?->academic_score_settings ?? [];
            $labels = [];

            foreach (['subjects' => 'subject', 'grades' => 'grade', 'assessments' => 'assessment'] as $setting => $name) {
                $labels[$name] = collect($settings[$setting] ?? [])
                    ->filter('is_array')
                    ->mapWithKeys(fn (array $item): array => [(string) ($item['key'] ?? '') => (string) ($item['label'] ?? $item['key'] ?? '')])
                    ->all();
            }

            $rows = $registration->academicScores->map(fn ($score): array => [
                'subject' => $labels['subject'][$score->subject_key] ?? (string) $score->subject_key,
                'grade' => $labels['grade'][$score->grade_key] ?? (string) $score->grade_key,
                'assessment' => $labels['assessment'][$score->assessment_key] ?? (string) $score->assessment_key,
                'score' => number_format((float) $score->score, 2, ',', '.'),
            ])->sortBy([
                ['subject', 'asc'], ['grade', 'asc'], ['assessment', 'asc'],
            ])->values()->all();

            $sections['academic_scores'] = ['key' => 'academic_scores', 'title' => 'Nilai Akademik', 'rows' => $rows];
        }

        if ($registration->achievements->isNotEmpty()) {
            $rows = $registration->achievements->map(fn ($achievement): array => [
                'title' => $this->displayValue($achievement->title),
                'level' => $this->displayValue($achievement->level),
                'year' => $this->displayValue($achievement->year),
                'organizer' => $this->displayValue($achievement->organizer),
                'description' => $this->displayValue($achievement->description),
            ])->all();

            $sections['achievements'] = ['key' => 'achievements', 'title' => 'Prestasi', 'rows' => $rows];
        }

        // The registration's immutable configuration determines the section order.
        $layout = collect($configuration?->form_layout ?? [])
            ->map(fn (mixed $item): ?string => is_array($item) ? ($item['key'] ?? null) : (is_string($item) ? $item : null))
            ->filter()
            ->values()
            ->all();

        $ordered = [];
        foreach (['registration_choice', ...$layout] as $key) {
            if (isset($sections[$key])) {
                $ordered[$key] = $sections[$key];
            }
        }
        foreach ($sections as $key => $section) {
            $ordered[$key] ??= $section;
        }

        return array_values(array_filter($ordered, fn (array $section): bool => $section['rows'] !== []));
    }

    /** @param array<string, mixed> $definition */
    private function row(string $label, mixed $value, array $definition = []): array
    {
        return ['label' => $label, 'value' => $this->displayValue($value, $definition)];
    }

    /** @param array<string, mixed> $definition */
    private function displayValue(mixed $value, array $definition = []): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }
        if (is_array($value)) {
            return collect($value)->map(fn (mixed $item): string => $this->displayValue($item))->implode(', ');
        }
        if ($value === null || $value === '') {
            return '—';
        }
        $options = $definition['options'] ?? [];
        if (($definition['type'] ?? '') === 'select' && is_array($options)) {
            foreach ($options as $optionKey => $option) {
                if (is_array($option) && (string) ($option['value'] ?? $option['key'] ?? '') === (string) $value) {
                    return (string) ($option['label'] ?? $value);
                }
                if (is_string($option) && (string) $optionKey === (string) $value) {
                    return $option;
                }
            }
        }

        return (string) $value;
    }
}
