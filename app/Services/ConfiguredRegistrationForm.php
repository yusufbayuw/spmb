<?php

namespace App\Services;

use App\Models\UnitConfiguration;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConfiguredRegistrationForm
{
    public const CORE_FIELDS = ['nik', 'full_name', 'gender', 'birth_place', 'birth_date', 'home_address', 'unit_id', 'registration_opening_id', 'registration_pathway_id', 'registrant_type', 'registrant_relationship', 'unit_configuration_id'];

    public const REGION_FIELDS = ['province_code', 'city_code', 'district_code', 'village_code'];

    public const BUILTIN_FIELDS = ['nickname', 'religion', 'phone', 'email', 'province_code', 'city_code', 'district_code', 'village_code', 'previous_school', 'graduation_year', 'father_name', 'father_nik', 'father_birth_place', 'father_birth_date', 'father_education', 'father_occupation', 'father_phone', 'father_email', 'father_income', 'mother_name', 'mother_nik', 'mother_birth_place', 'mother_birth_date', 'mother_education', 'mother_occupation', 'mother_phone', 'mother_email', 'mother_income'];

    public const BUILTIN_FIELD_POLICIES = [
        'system_default' => 'Gunakan bawaan sistem',
        'all_required' => 'Wajibkan semua isian bawaan',
    ];

    public static function fieldLabels(): array
    {
        $labels = ['nickname' => 'Nama panggilan', 'religion' => 'Agama', 'phone' => 'Telepon peserta', 'email' => 'Email peserta', 'province_code' => 'Provinsi', 'city_code' => 'Kabupaten/Kota', 'district_code' => 'Kecamatan', 'village_code' => 'Desa/Kelurahan', 'previous_school' => 'Sekolah asal', 'graduation_year' => 'Tahun lulus'];
        foreach (['father' => 'Ayah', 'mother' => 'Ibu'] as $prefix => $parent) {
            foreach (['name' => 'Nama', 'nik' => 'NIK', 'birth_place' => 'Tempat lahir', 'birth_date' => 'Tanggal lahir', 'education' => 'Pendidikan', 'occupation' => 'Pekerjaan', 'phone' => 'Telepon', 'email' => 'Email', 'income' => 'Penghasilan'] as $key => $label) {
                $labels[$prefix.'_'.$key] = $label.' '.$parent;
            }
        }

        return $labels;
    }

    public function hasActiveRegionFields(?UnitConfiguration $configuration): bool
    {
        if (! $configuration) {
            return false;
        }

        return collect(self::REGION_FIELDS)
            ->contains(fn (string $key): bool => (bool) ($this->builtinFieldState($configuration, $key)['active'] ?? false));
    }

    /**
     * Resolve the effective built-in field state after applying the unit-wide
     * policy and an optional per-field override.
     *
     * A null state means "leave the component's system default unchanged".
     *
     * @return array{active:?bool,required:?bool,overridden:bool}
     */
    public function builtinFieldState(?UnitConfiguration $configuration, string $key): array
    {
        if (! $configuration || ! in_array($key, self::BUILTIN_FIELDS, true)) {
            return ['active' => null, 'required' => null, 'overridden' => false];
        }

        $definition = collect($configuration->fields ?? [])
            ->first(fn (array $field): bool => ($field['key'] ?? null) === $key);

        if ($definition) {
            return [
                'active' => (bool) ($definition['active'] ?? false),
                'required' => (bool) ($definition['required'] ?? false),
                'overridden' => true,
            ];
        }

        if (($configuration->builtin_field_policy ?? 'system_default') === 'all_required') {
            return ['active' => true, 'required' => true, 'overridden' => false];
        }

        return ['active' => null, 'required' => null, 'overridden' => false];
    }

    public function apply(array $components, ?UnitConfiguration $configuration): array
    {
        if (! $configuration) {
            return array_values($components);
        }

        $definitions = collect($configuration->fields)->keyBy('key');
        $walk = function (array $items) use (&$walk, $definitions, $configuration): array {
            foreach ($items as $itemKey => $item) {
                if ($item instanceof Field && in_array($item->getName(), self::BUILTIN_FIELDS, true)) {
                    $fieldName = $item->getName();
                    $definition = $definitions->get($fieldName);
                    $state = $this->builtinFieldState($configuration, $fieldName);

                    if ($definition && in_array($fieldName, ['father_income', 'mother_income'], true)) {
                        $item = $this->configuredBuiltinField($fieldName, $definition);
                    } elseif ($definition) {
                        $item->label($definition['label'])->helperText($definition['help'] ?? null);
                    }

                    if ($state['active'] !== null) {
                        $item->visible($state['active']);
                    }

                    if ($state['required'] !== null) {
                        $item->required($state['required']);
                    }
                }

                if (! $item instanceof Field) {
                    $children = $item->getChildComponents();
                    if ($children) {
                        $item->schema($walk($children));
                    }
                }

                $items[$itemKey] = $item;
            }

            $groups = [];
            $ordered = [];

            foreach ($items as $itemKey => $item) {
                $definition = $item instanceof Field ? $definitions->get($item->getName()) : null;

                if ($definition && ! empty($definition['group'])) {
                    $groups[$definition['group']][] = $item;
                } else {
                    $ordered[$itemKey] = $item;
                }
            }

            $sort = fn (array $fields): array => collect($fields)
                ->sortBy(fn ($field) => $field instanceof Field && $definitions->has($field->getName())
                    ? array_search($field->getName(), $definitions->keys()->all(), true)
                    : -1)
                ->values()
                ->all();

            if (array_is_list($items)) {
                $ordered = $sort(array_values($ordered));
            }

            foreach ($groups as $label => $fields) {
                $ordered[] = Fieldset::make($label)->schema($sort($fields));
            }

            return $ordered;
        };

        $components = $walk($components);
        $formGroups = $this->formGroups($configuration);
        $customFieldsByGroup = [];

        foreach ($configuration->fields as $field) {
            if (! $field['active'] || in_array($field['key'], self::BUILTIN_FIELDS, true)) {
                continue;
            }

            $groupKey = filled($field['group_key'] ?? null)
                ? (string) $field['group_key']
                : $this->legacyGroupKey(($field['group'] ?? null) ?: 'Informasi Tambahan');

            $customFieldsByGroup[$groupKey][] = $this->field($field);
        }

        foreach ($formGroups as $group) {
            $fields = $customFieldsByGroup[$group['key']] ?? [];

            if ($fields === []) {
                continue;
            }

            $components['group:'.$group['key']] = Section::make($group['label'])
                ->schema($fields)
                ->columns(2);

            unset($customFieldsByGroup[$group['key']]);
        }

        foreach ($customFieldsByGroup as $groupKey => $fields) {
            $components['group:'.$groupKey] = Section::make('Informasi Tambahan')
                ->schema($fields)
                ->columns(2);
        }

        if ($configuration->academic_scores_enabled) {
            $settings = $configuration->academic_score_settings ?? [];
            $scoreFields = [];

            foreach ($settings['grades'] ?? [] as $grade) {
                $gradeFields = [];

                foreach ($settings['subjects'] ?? [] as $subject) {
                    $subjectFields = [];

                    foreach ($settings['assessments'] ?? [] as $assessment) {
                        $subjectFields[] = TextInput::make(
                            'academic_scores.'.($grade['key'] ?? '').'.'.($subject['key'] ?? '').'.'.($assessment['key'] ?? '')
                        )
                            ->label($assessment['label'] ?? $assessment['key'] ?? 'Nilai')
                            ->numeric()
                            ->minValue((float) ($settings['min_score'] ?? 0))
                            ->maxValue((float) ($settings['max_score'] ?? 100))
                            ->required((bool) ($settings['required'] ?? false));
                    }

                    $gradeFields[] = Fieldset::make($subject['label'] ?? $subject['key'] ?? 'Mata Pelajaran')
                        ->schema($subjectFields)
                        ->columns(min(4, max(1, count($subjectFields))));
                }

                $scoreFields[] = Section::make($grade['label'] ?? $grade['key'] ?? 'Kelas')
                    ->schema($gradeFields)
                    ->compact()
                    ->collapsible();
            }

            $components['academic_scores'] = Section::make('Data Nilai')
                ->description('Isikan nilai sesuai komponen yang diminta unit tujuan.')
                ->schema($scoreFields)
                ->visible(fn ($get): bool => app(RegistrationSupplementalDataService::class)->featureApplies(
                    true,
                    $settings,
                    $get('registration_pathway_uuid'),
                ));
        }

        if ($configuration->achievements_enabled) {
            $settings = $configuration->achievement_settings ?? [];
            $levels = array_combine($settings['levels'] ?? [], $settings['levels'] ?? []);

            $components['achievements'] = Section::make('Prestasi yang Pernah Diraih')
                ->description('Tambahkan prestasi yang relevan dengan jalur pendaftaran.')
                ->schema([
                    Repeater::make('achievements')
                        ->label('Prestasi')
                        ->default([])
                        ->maxItems((int) ($settings['max_entries'] ?? 3))
                        ->minItems((bool) ($settings['required'] ?? false) ? 1 : 0)
                        ->schema([
                            TextInput::make('title')->label('Nama Prestasi')->required()->maxLength(200)->columnSpan(2),
                            Select::make('level')->label('Tingkat')->options($levels)->required(),
                            TextInput::make('year')->label('Tahun')->numeric()->minValue(1900)->maxValue(now()->year + 1)->visible((bool) ($settings['show_year'] ?? false)),
                            TextInput::make('organizer')->label('Penyelenggara')->maxLength(200)->visible((bool) ($settings['show_organizer'] ?? false)),
                            Textarea::make('description')->label('Keterangan')->rows(2)->maxLength(2000)->columnSpanFull()->visible((bool) ($settings['show_description'] ?? false)),
                        ])
                        ->columns(2)
                        ->itemLabel(fn (array $state): string => $state['title'] ?? 'Prestasi'),
                ])
                ->visible(fn ($get): bool => app(RegistrationSupplementalDataService::class)->featureApplies(
                    true,
                    $settings,
                    $get('registration_pathway_uuid'),
                ));
        }

        $ordered = [];
        foreach ($this->formLayout($configuration, $formGroups) as $key) {
            if (array_key_exists($key, $components)) {
                $ordered[] = $components[$key];
                unset($components[$key]);
            }
        }

        foreach ($components as $component) {
            $ordered[] = $component;
        }

        return $ordered;
    }

    /** @return list<array{key:string,label:string}> */
    private function formGroups(UnitConfiguration $configuration): array
    {
        $configured = collect(is_array($configuration->form_groups) ? $configuration->form_groups : [])
            ->filter(fn (mixed $group): bool => is_array($group) && filled($group['key'] ?? null) && filled($group['label'] ?? null))
            ->map(fn (array $group): array => [
                'key' => (string) $group['key'],
                'label' => trim((string) $group['label']),
            ])
            ->values()
            ->all();

        if ($configured !== []) {
            return $configured;
        }

        $groups = [];
        foreach ($configuration->fields as $field) {
            if (! ($field['active'] ?? false) || in_array($field['key'] ?? null, self::BUILTIN_FIELDS, true)) {
                continue;
            }

            $label = filled($field['group'] ?? null) ? trim((string) $field['group']) : 'Informasi Tambahan';
            $key = $this->legacyGroupKey($label);

            if (! isset($groups[$key])) {
                $groups[$key] = ['key' => $key, 'label' => $label];
            }
        }

        return array_values($groups ?: [
            'group_additional' => ['key' => 'group_additional', 'label' => 'Informasi Tambahan'],
        ]);
    }

    /**
     * @param list<array{key:string,label:string}> $formGroups
     * @return list<string>
     */
    private function formLayout(UnitConfiguration $configuration, array $formGroups): array
    {
        $available = [
            'registration_choice',
            'identity',
            'parents',
            ...array_map(fn (array $group): string => 'group:'.$group['key'], $formGroups),
            'academic_scores',
            'achievements',
        ];

        $configured = collect(is_array($configuration->form_layout) ? $configuration->form_layout : [])
            ->map(fn (mixed $item): ?string => is_array($item) ? ($item['key'] ?? null) : (is_string($item) ? $item : null))
            ->filter(fn (?string $key): bool => $key !== null && in_array($key, $available, true))
            ->unique()
            ->values()
            ->all();

        if ($configured === []) {
            return $available;
        }

        $configured = array_values(array_filter($configured, fn (string $key): bool => $key !== 'registration_choice'));
        array_unshift($configured, 'registration_choice');

        foreach ($available as $key) {
            if (! in_array($key, $configured, true)) {
                $configured[] = $key;
            }
        }

        return $configured;
    }

    private function legacyGroupKey(string $label): string
    {
        return $label === 'Informasi Tambahan'
            ? 'group_additional'
            : 'group_'.substr(sha1(mb_strtolower(trim($label))), 0, 10);
    }

    private function configuredBuiltinField(string $name, array $definition): Field
    {
        $options = array_combine($definition['options'] ?? [], $definition['options'] ?? []);

        $field = match ($definition['type']) {
            'textarea' => Textarea::make($name)->maxLength(100),
            'number' => TextInput::make($name)->numeric()->minValue(0),
            'date' => DatePicker::make($name)->native(false),
            'select', 'multiselect' => Select::make($name)
                ->options($options)
                ->afterStateHydrated(function (Select $component, mixed $state) use ($options): void {
                    if (blank($state) || array_key_exists((string) $state, $options)) {
                        return;
                    }

                    $legacyLabel = is_numeric($state)
                        ? 'Rp '.number_format((float) $state, 0, ',', '.').' (nilai sebelumnya)'
                        : (string) $state.' (nilai sebelumnya)';

                    $component->options([(string) $state => $legacyLabel] + $options);
                })
                ->multiple($definition['type'] === 'multiselect')
                ->searchable(),
            'boolean' => Select::make($name)->options(['1' => 'Ya', '0' => 'Tidak']),
            default => TextInput::make($name)->maxLength(100),
        };

        return $field
            ->label($definition['label'])
            ->helperText($definition['help'] ?? null);
    }

    public function field(array $definition): Field
    {
        $name = 'custom_answers.'.$definition['key'];
        $options = array_combine($definition['options'] ?? [], $definition['options'] ?? []);
        $field = match ($definition['type']) {
            'textarea' => Textarea::make($name)->maxLength(5000),
            'number' => TextInput::make($name)->numeric(),
            'date' => DatePicker::make($name),
            'select', 'multiselect' => Select::make($name)->options($options)->multiple($definition['type'] === 'multiselect'),
            'boolean' => Select::make($name)->options(['1' => 'Ya', '0' => 'Tidak']),
            default => TextInput::make($name)->maxLength(1000),
        };

        return $field->label($definition['label'])->helperText($definition['help'] ?? null)->required((bool) $definition['required']);
    }

    public function validateAnswers(?UnitConfiguration $configuration, array $answers): array
    {
        if (! $configuration) {
            return [];
        }
        $rules = [];
        foreach ($configuration->fields as $field) {
            if (! $field['active'] || in_array($field['key'], self::BUILTIN_FIELDS, true)) {
                continue;
            }
            $name = $field['key'];
            $rules[$name] = [$field['required'] ? 'required' : 'nullable'];
            $rules[$name][] = match ($field['type']) {
                'number' => 'numeric', 'date' => 'date', 'boolean' => 'boolean', 'multiselect' => 'array', default => 'string',
            };
            if (in_array($field['type'], ['text', 'textarea'], true)) {
                $rules[$name][] = 'max:5000';
            }
            if ($field['type'] === 'select') {
                $rules[$name][] = Rule::in($field['options']);
            } elseif ($field['type'] === 'multiselect') {
                $rules[$name.'.*'] = [Rule::in($field['options'])];
            }
        }

        return Validator::make($answers, $rules)->validate();
    }
}
