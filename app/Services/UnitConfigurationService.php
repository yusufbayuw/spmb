<?php

namespace App\Services;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\Selection;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Models\Village;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitConfigurationService
{
    public function authorize(User $user, int $unitId): void
    {
        Gate::forUser($user)->authorize('configureRegistration', Unit::findOrFail($unitId));
    }

    public function current(int $unitId): ?UnitConfiguration
    {
        return UnitConfiguration::query()->where('unit_id', $unitId)->where('status', 'published')->orderByDesc('version')->first();
    }

    public function defaults(Unit $unit): array
    {
        $labels = ['report_card' => 'Rapor', 'family_card' => 'Kartu Keluarga', 'birth_certificate' => 'Akta Kelahiran', 'photo' => 'Pas Foto', 'supporting_document' => 'Dokumen Pendukung'];
        $documents = [];
        foreach ($labels as $key => $label) {
            $documents[] = ['key' => $key, 'label' => $label, 'active' => $key !== 'report_card' || $unit->code !== 'SD', 'required' => $key !== 'supporting_document', 'max_files' => 1, 'formats' => $key === 'photo' ? ['jpg', 'png'] : ['pdf', 'jpg', 'png'], 'instructions' => '', 'template_path' => null];
        }
        $tests = $unit->admissionTests()->where('is_active', true)->get()->map(fn (AdmissionTest $test): array => $test->only(['id', 'name', 'study_program_id', 'is_required', 'result_type', 'passing_score']))->all();

        return [
            'payment_enabled' => true,
            'documents_enabled' => true,
            'tests_enabled' => count($tests) > 0,
            'selection_mode' => 'flexible',
            'post_announcement_enabled' => $unit->isHigherEducation(),
            'workflow_stage_labels' => Registration::STAGES,
            'workflow_blocks' => collect(Registration::DEFAULT_WORKFLOW_BLOCKS)->map(fn (string $key): array => ['key' => $key])->all(),
            'builtin_field_policy' => 'system_default',
            'academic_scores_enabled' => false,
            'academic_score_settings' => [
                'required' => false,
                'min_score' => 0,
                'max_score' => 100,
                'pathway_uuids' => [],
                'grades' => [],
                'subjects' => [],
                'assessments' => [],
            ],
            'achievements_enabled' => false,
            'achievement_settings' => [
                'required' => false,
                'max_entries' => 3,
                'pathway_uuids' => [],
                'levels' => ['Sekolah', 'Kecamatan', 'Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'],
                'show_year' => false,
                'show_organizer' => false,
                'show_description' => false,
            ],
            'fields' => [],
            'form_groups' => [
                ['key' => 'group_additional', 'label' => 'Informasi Tambahan'],
            ],
            'form_layout' => [
                ['key' => 'registration_choice'],
                ['key' => 'identity'],
                ['key' => 'parents'],
                ['key' => 'group:group_additional'],
                ['key' => 'academic_scores'],
                ['key' => 'achievements'],
            ],
            'document_requirements' => $documents,
            'test_definitions' => $tests,
            're_registration_requirements' => [],
        ];
    }

    public function initialize(Unit $unit): UnitConfiguration
    {
        return DB::transaction(function () use ($unit): UnitConfiguration {
            Unit::query()->lockForUpdate()->findOrFail($unit->id);
            $configuration = $this->current($unit->id);
            if (! $configuration) {
                $configuration = UnitConfiguration::create($this->defaults($unit) + ['unit_id' => $unit->id, 'version' => 1, 'status' => 'published', 'legacy' => true, 'published_at' => now()]);
            }
            Registration::query()->where('unit_id', $unit->id)->whereNull('unit_configuration_id')->update(['unit_configuration_id' => $configuration->id]);

            return $configuration;
        });
    }

    public function draft(Unit $unit, User $actor): UnitConfiguration
    {
        $this->authorize($actor, $unit->id);

        return DB::transaction(function () use ($unit): UnitConfiguration {
            Unit::query()->lockForUpdate()->findOrFail($unit->id);
            $existing = UnitConfiguration::query()->where('unit_id', $unit->id)->where('status', 'draft')->first();
            if ($existing) {
                return $existing;
            }
            $current = $this->initialize($unit);

            return UnitConfiguration::create($current->only(['payment_enabled', 'documents_enabled', 'tests_enabled', 'selection_mode', 'post_announcement_enabled', 'workflow_stage_labels', 'workflow_blocks', 'builtin_field_policy', 'academic_scores_enabled', 'academic_score_settings', 'achievements_enabled', 'achievement_settings', 'fields', 'form_groups', 'form_layout', 'document_requirements', 'test_definitions', 're_registration_requirements']) + ['unit_id' => $unit->id, 'version' => $current->version + 1, 'status' => 'draft']);
        });
    }

    /**
     * Apply the latest published configuration to active registrations that can
     * still be changed safely without invalidating completed selection work.
     *
     * @return array{updated:int,moved_to_tests:int,skipped:int}
     */
    public function applyCurrentToEligibleActiveRegistrations(Unit $unit, User $actor): array
    {
        $this->authorize($actor, $unit->id);

        $configuration = $this->current($unit->id);

        if (! $configuration) {
            throw ValidationException::withMessages([
                'configuration' => 'Belum ada konfigurasi terpublikasi untuk unit ini.',
            ]);
        }

        return DB::transaction(function () use ($unit, $configuration, $actor): array {
            Unit::query()->lockForUpdate()->findOrFail($unit->id);

            $registrations = Registration::query()
                ->with(['selection', 'announcement', 'testResults', 'configuration', 'unit', 'opening'])
                ->where('unit_id', $unit->id)
                ->where('lifecycle_status', 'active')
                ->where(function ($query) use ($configuration): void {
                    $query->whereNull('unit_configuration_id')
                        ->orWhere('unit_configuration_id', '!=', $configuration->id);
                })
                ->whereIn('current_stage', [
                    'data_validation',
                    'virtual_account',
                    'payment',
                    'payment_verification',
                    'applicant_card',
                    'documents',
                    'document_verification',
                    'tests',
                    'selection',
                ])
                ->lockForUpdate()
                ->get();

            $updated = 0;
            $movedToTests = 0;
            $skipped = 0;

            foreach ($registrations as $registration) {
                if ($this->workflowConfigurationChanged($registration->configuration, $configuration)) {
                    $skipped++;

                    continue;
                }

                if ($registration->current_stage !== 'data_validation'
                    && $this->supplementalConfigurationChanged($registration->configuration, $configuration)) {
                    $skipped++;

                    continue;
                }

                $hasAssessedTests = $registration->testResults->contains(
                    fn (AdmissionTestResult $result): bool => filled($result->assessed_at)
                        || in_array($result->status, ['completed', 'absent', 'exempted'], true),
                );

                if ($registration->current_stage === 'tests' && $hasAssessedTests) {
                    $skipped++;

                    continue;
                }

                if ($registration->current_stage === 'selection') {
                    $selection = $registration->selection;

                    if ($hasAssessedTests
                        || $registration->announcement
                        || ($selection && ($selection->decision !== 'pending' || $selection->selection_batch_id))) {
                        $skipped++;

                        continue;
                    }
                }

                $oldConfigurationId = $registration->unit_configuration_id;

                $registration->forceFill([
                    'unit_configuration_id' => $configuration->id,
                ])->saveQuietly();

                $registration->unsetRelation('configuration');
                $registration->load('configuration');

                $tests = $registration->configuredTests();
                $hasRequiredTests = collect($tests)->contains('is_required', true);
                $shouldMaterializeTests = $registration->current_stage === 'tests'
                    || ($registration->current_stage === 'selection' && $hasRequiredTests);

                if ($shouldMaterializeTests) {
                    foreach ($tests as $test) {
                        AdmissionTestResult::firstOrCreate(
                            [
                                'registration_id' => $registration->id,
                                'admission_test_id' => $test['id'],
                            ],
                            [
                                'status' => 'unbooked',
                                'result' => 'pending',
                            ],
                        );
                    }
                }

                if ($hasRequiredTests && $registration->current_stage === 'selection') {
                    $registration->forceFill([
                        'current_stage' => 'tests',
                    ])->saveQuietly();
                    $movedToTests++;
                } elseif (! $hasRequiredTests && $registration->current_stage === 'tests') {
                    Selection::firstOrCreate(
                        ['registration_id' => $registration->id],
                        ['decision' => 'pending'],
                    );

                    $registration->forceFill([
                        'current_stage' => 'selection',
                    ])->saveQuietly();
                }

                app(AuditTrail::class)->record(
                    'configuration.applied_to_active_registration',
                    $registration,
                    oldValues: ['unit_configuration_id' => $oldConfigurationId],
                    newValues: [
                        'unit_configuration_id' => $configuration->id,
                        'current_stage' => $registration->current_stage,
                    ],
                    metadata: ['configuration_version' => $configuration->version],
                    actor: $actor,
                    unitId: $unit->id,
                    registrationId: $registration->id,
                    description: 'Konfigurasi pendaftaran terpublikasi diterapkan ke pendaftaran aktif yang masih aman diperbarui.',
                );

                $updated++;
            }

            return [
                'updated' => $updated,
                'moved_to_tests' => $movedToTests,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Normalize legacy configuration data for the current editor/runtime shape.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizeEditorData(array $data): array
    {
        $workflowBlocks = $data['workflow_blocks'] ?? null;

        if (! is_array($workflowBlocks) || $workflowBlocks === []) {
            $workflowBlocks = collect(Registration::DEFAULT_WORKFLOW_BLOCKS)
                ->map(fn (string $key): array => ['key' => $key])
                ->all();
        } else {
            $workflowBlocks = collect($workflowBlocks)
                ->map(fn (mixed $block): array => ['key' => is_array($block) ? ($block['key'] ?? null) : $block])
                ->values()
                ->all();
        }

        $data['workflow_blocks'] = $workflowBlocks;

        $formGroups = collect(is_array($data['form_groups'] ?? null) ? $data['form_groups'] : [])
            ->filter(fn (mixed $group): bool => is_array($group) && filled($group['key'] ?? null) && filled($group['label'] ?? null))
            ->map(fn (array $group): array => [
                'key' => (string) $group['key'],
                'label' => trim((string) $group['label']),
            ])
            ->values()
            ->all();

        if ($formGroups === []) {
            $formGroups = [
                ['key' => 'group_additional', 'label' => 'Informasi Tambahan'],
            ];
        }

        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];

        foreach ($fields as $index => $field) {
            if (! is_array($field) || in_array($field['key'] ?? null, ConfiguredRegistrationForm::BUILTIN_FIELDS, true)) {
                continue;
            }

            if (filled($field['group_key'] ?? null)) {
                continue;
            }

            $groupLabel = filled($field['group'] ?? null)
                ? trim((string) $field['group'])
                : 'Informasi Tambahan';

            $groupKey = null;
            foreach ($formGroups as $group) {
                if (mb_strtolower($group['label']) === mb_strtolower($groupLabel)) {
                    $groupKey = $group['key'];
                    break;
                }
            }

            if (! $groupKey) {
                $groupKey = 'group_'.substr(sha1(mb_strtolower($groupLabel)), 0, 10);
                $existingKeys = array_column($formGroups, 'key');
                $suffix = 2;

                while (in_array($groupKey, $existingKeys, true)) {
                    $groupKey = 'group_'.substr(sha1(mb_strtolower($groupLabel)), 0, 8).'_'.$suffix;
                    $suffix++;
                }

                $formGroups[] = ['key' => $groupKey, 'label' => $groupLabel];
            }

            $fields[$index]['group_key'] = $groupKey;
            $fields[$index]['group'] = $groupLabel;
        }

        $data['fields'] = $fields;
        $data['form_groups'] = $formGroups;

        $availableLayoutKeys = [
            'registration_choice',
            'identity',
            'parents',
            ...array_map(fn (array $group): string => 'group:'.$group['key'], $formGroups),
            'academic_scores',
            'achievements',
        ];

        $configuredLayout = collect(is_array($data['form_layout'] ?? null) ? $data['form_layout'] : [])
            ->map(fn (mixed $item): ?string => is_array($item) ? ($item['key'] ?? null) : (is_string($item) ? $item : null))
            ->filter(fn (?string $key): bool => $key !== null && in_array($key, $availableLayoutKeys, true))
            ->unique()
            ->values()
            ->all();

        $configuredLayout = array_values(array_filter(
            $configuredLayout,
            fn (string $key): bool => $key !== 'registration_choice',
        ));
        array_unshift($configuredLayout, 'registration_choice');

        foreach ($availableLayoutKeys as $key) {
            if (! in_array($key, $configuredLayout, true)) {
                $configuredLayout[] = $key;
            }
        }

        $data['form_layout'] = array_map(fn (string $key): array => ['key' => $key], $configuredLayout);

        return $data;
    }

    private function workflowConfigurationChanged(?UnitConfiguration $from, UnitConfiguration $to): bool
    {
        $keys = function (?UnitConfiguration $configuration): array {
            $blocks = is_array($configuration?->workflow_blocks) && $configuration->workflow_blocks !== []
                ? $configuration->workflow_blocks
                : collect(Registration::DEFAULT_WORKFLOW_BLOCKS)->map(fn (string $key): array => ['key' => $key])->all();

            return collect($blocks)
                ->map(fn (mixed $block): ?string => is_array($block) ? ($block['key'] ?? null) : (is_string($block) ? $block : null))
                ->filter()
                ->values()
                ->all();
        };

        return $keys($from) !== $keys($to);
    }

    private function supplementalConfigurationChanged(?UnitConfiguration $from, UnitConfiguration $to): bool
    {
        $fromScoresEnabled = (bool) ($from?->academic_scores_enabled ?? false);
        $toScoresEnabled = (bool) $to->academic_scores_enabled;

        if ($fromScoresEnabled !== $toScoresEnabled) {
            return true;
        }

        if ($toScoresEnabled && ($from?->academic_score_settings ?? []) !== ($to->academic_score_settings ?? [])) {
            return true;
        }

        $fromAchievementsEnabled = (bool) ($from?->achievements_enabled ?? false);
        $toAchievementsEnabled = (bool) $to->achievements_enabled;

        if ($fromAchievementsEnabled !== $toAchievementsEnabled) {
            return true;
        }

        return $toAchievementsEnabled
            && ($from?->achievement_settings ?? []) !== ($to->achievement_settings ?? []);
    }

    public function save(UnitConfiguration $configuration, User $actor, array $data, bool $publish = false): UnitConfiguration
    {
        $this->authorize($actor, (int) $configuration->unit_id);

        return DB::transaction(function () use ($configuration, $data, $publish): UnitConfiguration {
            Unit::query()->lockForUpdate()->findOrFail($configuration->unit_id);
            $locked = UnitConfiguration::query()->lockForUpdate()->findOrFail($configuration->id);

            $data['builtin_field_policy'] ??= 'system_default';
            $data = $this->normalizeEditorData($data);
            $stageLabels = is_array($data['workflow_stage_labels'] ?? null) ? $data['workflow_stage_labels'] : [];
            $data['workflow_stage_labels'] = collect(Registration::STAGES)
                ->mapWithKeys(fn (string $defaultLabel, string $stage): array => [
                    $stage => filled($stageLabels[$stage] ?? null)
                        ? trim((string) $stageLabels[$stage])
                        : $defaultLabel,
                ])
                ->all();
            $data['academic_scores_enabled'] ??= false;
            $data['academic_score_settings'] = array_replace([
                'required' => false,
                'min_score' => 0,
                'max_score' => 100,
                'pathway_uuids' => [],
                'grades' => [],
                'subjects' => [],
                'assessments' => [],
            ], is_array($data['academic_score_settings'] ?? null) ? $data['academic_score_settings'] : []);
            $data['achievements_enabled'] ??= false;
            $data['achievement_settings'] = array_replace([
                'required' => false,
                'max_entries' => 3,
                'pathway_uuids' => [],
                'levels' => ['Sekolah', 'Kecamatan', 'Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'],
                'show_year' => false,
                'show_organizer' => false,
                'show_description' => false,
            ], is_array($data['achievement_settings'] ?? null) ? $data['achievement_settings'] : []);

            $validated = Validator::make($data, [
                'payment_enabled' => ['required', 'boolean'], 'documents_enabled' => ['required', 'boolean'], 'tests_enabled' => ['required', 'boolean'], 'selection_mode' => ['required', Rule::in(['manual', 'batch', 'flexible'])], 'post_announcement_enabled' => ['required', 'boolean'],
                'workflow_stage_labels' => ['present', 'array'], 'workflow_stage_labels.*' => ['required', 'string', 'max:120'],
                'workflow_blocks' => ['present', 'array', 'size:2'],
                'workflow_blocks.*.key' => ['required', Rule::in(array_keys(Registration::WORKFLOW_BLOCK_LABELS)), 'distinct'],
                'builtin_field_policy' => ['required', Rule::in(array_keys(ConfiguredRegistrationForm::BUILTIN_FIELD_POLICIES))],
                'academic_scores_enabled' => ['required', 'boolean'],
                'academic_score_settings' => ['present', 'array'],
                'academic_score_settings.required' => ['required', 'boolean'],
                'academic_score_settings.min_score' => ['required', 'numeric', 'min:0'],
                'academic_score_settings.max_score' => ['required', 'numeric', 'gt:academic_score_settings.min_score'],
                'academic_score_settings.pathway_uuids' => ['present', 'array'],
                'academic_score_settings.pathway_uuids.*' => ['uuid'],
                'academic_score_settings.grades' => ['present', 'array', 'max:12'],
                'academic_score_settings.grades.*.key' => ['required', 'regex:/^[a-z0-9_]+$/', 'distinct', 'max:60'],
                'academic_score_settings.grades.*.label' => ['required', 'string', 'max:100'],
                'academic_score_settings.subjects' => ['present', 'array', 'max:50'],
                'academic_score_settings.subjects.*.key' => ['required', 'regex:/^[a-z0-9_]+$/', 'distinct', 'max:60'],
                'academic_score_settings.subjects.*.label' => ['required', 'string', 'max:150'],
                'academic_score_settings.assessments' => ['present', 'array', 'max:20'],
                'academic_score_settings.assessments.*.key' => ['required', 'regex:/^[a-z0-9_]+$/', 'distinct', 'max:60'],
                'academic_score_settings.assessments.*.label' => ['required', 'string', 'max:150'],
                'academic_score_settings.assessments.*.grade_keys' => ['nullable', 'array', 'max:12'],
                'academic_score_settings.assessments.*.grade_keys.*' => ['string', 'distinct', 'max:60'],
                'achievements_enabled' => ['required', 'boolean'],
                'achievement_settings' => ['present', 'array'],
                'achievement_settings.required' => ['required', 'boolean'],
                'achievement_settings.max_entries' => ['required', 'integer', 'min:1', 'max:20'],
                'achievement_settings.pathway_uuids' => ['present', 'array'],
                'achievement_settings.pathway_uuids.*' => ['uuid'],
                'achievement_settings.levels' => ['present', 'array', 'max:30'],
                'achievement_settings.levels.*' => ['required', 'string', 'distinct', 'max:100'],
                'achievement_settings.show_year' => ['required', 'boolean'],
                'achievement_settings.show_organizer' => ['required', 'boolean'],
                'achievement_settings.show_description' => ['required', 'boolean'],
                'fields' => ['present', 'array', 'max:100'], 'fields.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct', 'max:60'],
                'fields.*.label' => ['required', 'string', 'max:150'], 'fields.*.type' => ['required', Rule::in(['text', 'textarea', 'number', 'date', 'select', 'multiselect', 'boolean', 'file'])],
                'fields.*.active' => ['required', 'boolean'], 'fields.*.required' => ['required', 'boolean'], 'fields.*.group' => ['nullable', 'string', 'max:100'],
                'fields.*.group_key' => ['nullable', 'regex:/^[a-z][a-z0-9_]*$/', 'max:60'],
                'fields.*.help' => ['nullable', 'string', 'max:1000'], 'fields.*.options' => ['nullable', 'array'], 'fields.*.options.*' => ['string', 'max:150'],
                'fields.*.formats' => ['nullable', 'array', 'max:4'], 'fields.*.formats.*' => [Rule::in(['pdf', 'docx', 'jpg', 'png'])],
                'fields.*.template_path' => ['nullable', 'string'],
                'form_groups' => ['present', 'array', 'max:30'],
                'form_groups.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct', 'max:60'],
                'form_groups.*.label' => ['required', 'string', 'distinct', 'max:100'],
                'form_layout' => ['present', 'array', 'max:40'],
                'form_layout.*.key' => ['required', 'string', 'distinct', 'max:100'],
                'document_requirements' => ['present', 'array', 'max:100'], 'document_requirements.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'max:60', 'distinct'],
                'document_requirements.*.label' => ['required', 'string', 'max:150'], 'document_requirements.*.active' => ['required', 'boolean'], 'document_requirements.*.required' => ['required', 'boolean'],
                'document_requirements.*.max_files' => ['required', 'integer', 'min:1', 'max:20'], 'document_requirements.*.formats' => ['required', 'array', 'min:1'],
                'document_requirements.*.formats.*' => [Rule::in(['pdf', 'docx', 'jpg', 'png'])], 'document_requirements.*.instructions' => ['nullable', 'string', 'max:2000'],
                'document_requirements.*.template_path' => ['nullable', 'string'], 'test_definitions' => ['present', 'array'],
                'test_definitions.*.id' => ['required', 'integer', 'distinct'],
                're_registration_requirements' => ['present', 'array', 'max:100'],
                're_registration_requirements.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'max:60', 'distinct'],
                're_registration_requirements.*.label' => ['required', 'string', 'max:150'],
                're_registration_requirements.*.type' => ['required', Rule::in(['checklist', 'document', 'payment', 'information'])],
                're_registration_requirements.*.active' => ['required', 'boolean'],
                're_registration_requirements.*.required' => ['required', 'boolean'],
                're_registration_requirements.*.instructions' => ['nullable', 'string', 'max:2000'],
            ])->validate();
            $workflowKeys = collect($validated['workflow_blocks'])->pluck('key')->values()->all();
            if (array_diff(Registration::DEFAULT_WORKFLOW_BLOCKS, $workflowKeys) !== []
                || array_diff($workflowKeys, Registration::DEFAULT_WORKFLOW_BLOCKS) !== []) {
                throw ValidationException::withMessages([
                    'workflow_blocks' => 'Urutan pra-seleksi hanya boleh terdiri dari Kartu Pendaftar dan Berkas.',
                ]);
            }

            $groupKeys = collect($validated['form_groups'])->pluck('key');
            foreach ($validated['fields'] as $field) {
                if (in_array($field['key'], ConfiguredRegistrationForm::BUILTIN_FIELDS, true)) {
                    continue;
                }

                if (blank($field['group_key'] ?? null) || ! $groupKeys->contains($field['group_key'])) {
                    throw ValidationException::withMessages([
                        'fields' => 'Setiap pertanyaan tambahan harus berada pada kelompok formulir yang valid.',
                    ]);
                }
            }

            $availableLayoutKeys = collect([
                'registration_choice',
                'identity',
                'parents',
                ...$groupKeys->map(fn (string $key): string => 'group:'.$key)->all(),
                'academic_scores',
                'achievements',
            ]);
            $layoutKeys = collect($validated['form_layout'])->pluck('key');

            if ($layoutKeys->first() !== 'registration_choice'
                || $availableLayoutKeys->diff($layoutKeys)->isNotEmpty()
                || $layoutKeys->diff($availableLayoutKeys)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'form_layout' => 'Struktur formulir tidak valid. Pilihan Pendaftaran harus tetap pertama dan seluruh bagian harus tercantum satu kali.',
                ]);
            }

            $academicGradeKeys = collect($validated['academic_score_settings']['grades'] ?? [])->pluck('key');
            foreach ($validated['academic_score_settings']['assessments'] ?? [] as $assessmentIndex => $assessment) {
                $unknownGradeKeys = collect($assessment['grade_keys'] ?? [])->diff($academicGradeKeys);

                if ($unknownGradeKeys->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'academic_score_settings.assessments.'.$assessmentIndex.'.grade_keys'
                            => 'Komponen nilai hanya boleh diterapkan pada kelas/tingkat yang tersedia.',
                    ]);
                }
            }

            $pathwayUuids = collect($validated['academic_score_settings']['pathway_uuids'] ?? [])
                ->merge($validated['achievement_settings']['pathway_uuids'] ?? [])
                ->filter()
                ->unique()
                ->values();

            if ($pathwayUuids->isNotEmpty()) {
                $matchingPathways = $locked->unit->registrationPathways()
                    ->whereIn('uuid', $pathwayUuids)
                    ->count();

                if ($matchingPathways !== $pathwayUuids->count()) {
                    throw ValidationException::withMessages([
                        'configuration' => 'Jalur yang dipilih untuk Nilai/Prestasi harus berasal dari unit yang sama.',
                    ]);
                }
            }

            if ($validated['academic_scores_enabled']) {
                foreach (['grades' => 'kelas', 'subjects' => 'mata pelajaran', 'assessments' => 'komponen nilai'] as $key => $label) {
                    if (empty($validated['academic_score_settings'][$key])) {
                        throw ValidationException::withMessages([
                            'academic_score_settings' => "Saat Data Nilai aktif, minimal satu {$label} harus dikonfigurasi.",
                        ]);
                    }
                }
            }

            if ($validated['achievements_enabled'] && empty($validated['achievement_settings']['levels'])) {
                throw ValidationException::withMessages([
                    'achievement_settings' => 'Saat Prestasi aktif, minimal satu tingkat prestasi harus tersedia.',
                ]);
            }

            foreach ($validated['fields'] as $field) {
                if (in_array($field['key'], ConfiguredRegistrationForm::CORE_FIELDS, true)) {
                    throw ValidationException::withMessages(['fields' => 'Identitas inti tidak boleh diubah.']);
                }
                if (in_array($field['type'], ['select', 'multiselect'], true)
                    && empty($field['options'])
                    && ! in_array($field['key'], ConfiguredRegistrationForm::BUILTIN_FIELDS, true)) {
                    throw ValidationException::withMessages(['fields' => 'Field pilihan harus memiliki opsi.']);
                }
            }
            $fieldsByKey = collect($validated['fields'])->keyBy('key');
            $allBuiltinsRequired = $validated['builtin_field_policy'] === 'all_required';
            $effectiveBuiltin = function (string $key) use ($fieldsByKey, $allBuiltinsRequired): array {
                $override = $fieldsByKey->get($key);

                if ($override) {
                    return [
                        'active' => (bool) ($override['active'] ?? false),
                        'required' => (bool) ($override['required'] ?? false),
                    ];
                }

                if ($allBuiltinsRequired && in_array($key, ConfiguredRegistrationForm::BUILTIN_FIELDS, true)) {
                    return ['active' => true, 'required' => true];
                }

                return ['active' => false, 'required' => false];
            };

            $regionHierarchy = [
                'city_code' => ['province_code'],
                'district_code' => ['province_code', 'city_code'],
                'village_code' => ['province_code', 'city_code', 'district_code'],
            ];

            foreach ($regionHierarchy as $fieldKey => $parentKeys) {
                $field = $effectiveBuiltin($fieldKey);

                if (! $field['active']) {
                    continue;
                }

                foreach ($parentKeys as $parentKey) {
                    $parent = $effectiveBuiltin($parentKey);

                    if (! $parent['active']) {
                        throw ValidationException::withMessages([
                            'fields' => 'Field wilayah harus diaktifkan berurutan: Provinsi → Kabupaten/Kota → Kecamatan → Desa/Kelurahan.',
                        ]);
                    }

                    if ($field['required'] && ! $parent['required']) {
                        throw ValidationException::withMessages([
                            'fields' => 'Jika field wilayah turunan wajib, seluruh field wilayah induknya juga harus wajib.',
                        ]);
                    }
                }
            }

            if ($publish) {
                $regionModels = [
                    'province_code' => Province::class,
                    'city_code' => Regency::class,
                    'district_code' => District::class,
                    'village_code' => Village::class,
                ];

                foreach ($regionModels as $fieldKey => $modelClass) {
                    if ($effectiveBuiltin($fieldKey)['active'] && ! $modelClass::query()->exists()) {
                        throw ValidationException::withMessages([
                            'fields' => 'Master wilayah Indonesia belum lengkap. Import master wilayah sebelum mempublikasikan field Provinsi/Kabupaten/Kecamatan/Desa.',
                        ]);
                    }
                }
            }

            foreach ($validated['fields'] as $field) {
                if (($field['type'] ?? null) !== 'file') {
                    continue;
                }

                if (in_array($field['key'], ConfiguredRegistrationForm::BUILTIN_FIELDS, true)) {
                    throw ValidationException::withMessages([
                        'fields' => 'Jenis Unggah Dokumen hanya dapat digunakan untuk field tambahan.',
                    ]);
                }

                $formats = array_values($field['formats'] ?? []);
                if ($formats === []) {
                    throw ValidationException::withMessages([
                        'fields' => 'Field Unggah Dokumen harus memiliki minimal satu format file.',
                    ]);
                }

                if (! empty($field['template_path'])) {
                    if (! str_starts_with($field['template_path'], 'templates/'.$locked->unit_id.'/')) {
                        throw ValidationException::withMessages(['fields' => 'Template field tidak sesuai unit.']);
                    }

                    app(ApplicantUploadSecurity::class)->inspect($field['template_path'], ['pdf', 'docx']);
                }
            }

            foreach ($validated['document_requirements'] as $requirement) {
                if (! empty($requirement['template_path'])) {
                    if (! str_starts_with($requirement['template_path'], 'templates/'.$locked->unit_id.'/')) {
                        throw ValidationException::withMessages(['document_requirements' => 'Template tidak sesuai unit.']);
                    }
                    app(ApplicantUploadSecurity::class)->inspect($requirement['template_path'], ['pdf', 'docx']);
                }
            }
            $tests = AdmissionTest::query()->where('unit_id', $locked->unit_id)->whereIn('id', array_column($validated['test_definitions'], 'id'))->get();
            if ($tests->count() !== count($validated['test_definitions'])) {
                throw ValidationException::withMessages(['test_definitions' => 'Tes tidak sesuai unit.']);
            }
            $validated['test_definitions'] = $tests->map(fn (AdmissionTest $test): array => $test->only(['id', 'name', 'study_program_id', 'is_required', 'result_type', 'passing_score']))->all();
            if ($publish) {
                if ($validated['tests_enabled'] && ! $tests->contains('is_required', true)) {
                    throw ValidationException::withMessages(['test_definitions' => 'Aktifkan minimal satu tes wajib.']);
                }
                if (! $validated['payment_enabled'] && $locked->unit->registrationOpenings()->where('status', 'open')->where('registration_fee', '>', 0)->exists()) {
                    throw ValidationException::withMessages(['payment_enabled' => 'Pembukaan aktif harus berbiaya nol sebelum pembayaran dinonaktifkan.']);
                }
                $validated += ['status' => 'published', 'published_at' => now()];
            }
            $locked->update($validated);
            app(AuditTrail::class)->record($publish ? 'configuration.published' : 'configuration.saved', $locked, unitId: $locked->unit_id);

            return $locked;
        });
    }
}
