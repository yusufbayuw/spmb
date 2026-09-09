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
        return collect($configuration?->fields ?? [])
            ->contains(fn (array $field): bool => (bool) ($field['active'] ?? false)
                && in_array($field['key'] ?? null, self::REGION_FIELDS, true));
    }

    public function apply(array $components, ?UnitConfiguration $configuration): array
    {
        if (! $configuration) {
            return $components;
        }
        $definitions = collect($configuration->fields)->keyBy('key');
        $walk = function (array $items) use (&$walk, $definitions): array {
            foreach ($items as $item) {
                if ($item instanceof Field && in_array($item->getName(), self::BUILTIN_FIELDS, true) && ($definition = $definitions->get($item->getName()))) {
                    $item->label($definition['label'])->helperText($definition['help'] ?? null)->visible((bool) $definition['active'])->required((bool) $definition['required']);
                }
                if (! $item instanceof Field) {
                    $children = $item->getChildComponents();
                    if ($children) {
                        $item->schema($walk($children));
                    }
                }
            }

            $groups = [];
            $ordered = [];
            foreach ($items as $index => $item) {
                $definition = $item instanceof Field ? $definitions->get($item->getName()) : null;
                if ($definition && ! empty($definition['group'])) {
                    $groups[$definition['group']][] = $item;
                } else {
                    $ordered[] = $item;
                }
            }
            $sort = fn (array $fields): array => collect($fields)->sortBy(fn ($field) => $field instanceof Field && $definitions->has($field->getName()) ? array_search($field->getName(), $definitions->keys()->all(), true) : -1)->values()->all();
            $ordered = $sort($ordered);
            foreach ($groups as $label => $fields) {
                $ordered[] = Fieldset::make($label)->schema($sort($fields));
            }

            return $ordered;
        };
        $components = $walk($components);
        $groups = [];
        foreach ($configuration->fields as $field) {
            if (! $field['active'] || in_array($field['key'], self::BUILTIN_FIELDS, true)) {
                continue;
            }
            $groups[($field['group'] ?? null) ?: 'Informasi Tambahan'][] = $this->field($field);
        }
        foreach ($groups as $label => $fields) {
            $components[] = Section::make($label)->schema($fields)->columns(2);
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

            $components[] = Section::make('Data Nilai')
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

            $components[] = Section::make('Prestasi yang Pernah Diraih')
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
                            TextInput::make('year')->label('Tahun')->numeric()->minValue(1900)->maxValue(now()->year + 1),
                            TextInput::make('organizer')->label('Penyelenggara')->maxLength(200),
                            Textarea::make('description')->label('Keterangan')->rows(2)->maxLength(2000)->columnSpanFull(),
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

        return $components;
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
