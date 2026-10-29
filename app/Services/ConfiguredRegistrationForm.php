<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\UnitConfiguration;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConfiguredRegistrationForm
{
    public const CORE_FIELDS = ['nik', 'full_name', 'gender', 'birth_place', 'birth_date', 'home_address', 'unit_id', 'registration_opening_id', 'registration_pathway_id', 'registrant_type', 'registrant_relationship', 'unit_configuration_id'];

    public const REGION_FIELDS = ['province_code', 'city_code', 'district_code', 'village_code'];

    public const BUILTIN_FIELDS = ['nisn', 'nickname', 'religion', 'phone', 'email', 'rt', 'rw', 'province_code', 'city_code', 'district_code', 'village_code', 'previous_school', 'graduation_year', 'father_name', 'father_nik', 'father_birth_place', 'father_birth_date', 'father_education', 'father_occupation', 'father_workplace', 'father_phone', 'father_email', 'father_income', 'mother_name', 'mother_nik', 'mother_birth_place', 'mother_birth_date', 'mother_education', 'mother_occupation', 'mother_workplace', 'mother_phone', 'mother_email', 'mother_income'];

    public const BUILTIN_FIELD_POLICIES = [
        'system_default' => 'Gunakan bawaan sistem',
        'all_required' => 'Wajibkan semua isian bawaan',
    ];

    public static function fieldLabels(): array
    {
        $labels = ['nisn' => 'NISN', 'nickname' => 'Nama panggilan', 'religion' => 'Agama', 'phone' => 'Telepon peserta', 'email' => 'Email peserta', 'rt' => 'RT', 'rw' => 'RW', 'province_code' => 'Provinsi', 'city_code' => 'Kabupaten/Kota', 'district_code' => 'Kecamatan', 'village_code' => 'Desa/Kelurahan', 'previous_school' => 'Sekolah asal', 'graduation_year' => 'Tahun lulus'];
        foreach (['father' => 'Ayah', 'mother' => 'Ibu'] as $prefix => $parent) {
            foreach (['name' => 'Nama', 'nik' => 'NIK', 'birth_place' => 'Tempat lahir', 'birth_date' => 'Tanggal lahir', 'education' => 'Pendidikan', 'occupation' => 'Pekerjaan', 'workplace' => 'Instansi / Tempat Kerja', 'phone' => 'Telepon', 'email' => 'Email', 'income' => 'Penghasilan'] as $key => $label) {
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

        // Applicants may not have an NISN yet; only an explicit field override makes it required.
        if ($key === 'nisn') {
            return ['active' => true, 'required' => false, 'overridden' => false];
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

        $effectiveFields = $this->presentationFields($configuration);
        $definitions = collect($effectiveFields)->keyBy('key');
        $walk = function (array $items) use (&$walk, $definitions, $configuration): array {
            foreach ($items as $itemKey => $item) {
                if ($item instanceof Field && in_array($item->getName(), self::BUILTIN_FIELDS, true)) {
                    $fieldName = $item->getName();
                    $definition = $definitions->get($fieldName);
                    $state = $this->builtinFieldState($configuration, $fieldName);

                    if ($definition) {
                        $item = $this->configuredBuiltinComponent($item, $definition);
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

        foreach ($effectiveFields as $field) {
            if (! $field['active'] || in_array($field['key'], self::BUILTIN_FIELDS, true)) {
                continue;
            }

            $groupKey = filled($field['group_key'] ?? null)
                ? (string) $field['group_key']
                : $this->legacyGroupKey(($field['group'] ?? null) ?: 'Informasi Tambahan');

            $customFieldsByGroup[$groupKey][] = $this->field($field, $configuration);
        }

        foreach ($formGroups as $group) {
            $fields = $customFieldsByGroup[$group['key']] ?? [];

            if ($fields === []) {
                continue;
            }

            $components['group:'.$group['key']] = Section::make($group['label'])
                ->schema($fields)
                ->columns(['default' => 1, 'md' => 2]);

            unset($customFieldsByGroup[$group['key']]);
        }

        foreach ($customFieldsByGroup as $groupKey => $fields) {
            $components['group:'.$groupKey] = Section::make('Informasi Tambahan')
                ->schema($fields)
                ->columns(['default' => 1, 'md' => 2]);
        }

        if ($configuration->academic_scores_enabled) {
            $settings = $configuration->academic_score_settings ?? [];
            $scoreFields = [];

            foreach ($settings['grades'] ?? [] as $grade) {
                $gradeFields = [];

                foreach ($settings['subjects'] ?? [] as $subject) {
                    $subjectFields = [];

                    foreach (app(RegistrationSupplementalDataService::class)->assessmentsForGrade(
                        $settings,
                        (string) ($grade['key'] ?? ''),
                    ) as $assessment) {
                        $subjectFields[] = TextInput::make(
                            'academic_scores.'.($grade['key'] ?? '').'.'.($subject['key'] ?? '').'.'.($assessment['key'] ?? '')
                        )
                            ->label($assessment['label'] ?? $assessment['key'] ?? 'Nilai')
                            ->numeric()
                            ->minValue((float) ($settings['min_score'] ?? 0))
                            ->maxValue((float) ($settings['max_score'] ?? 100))
                            ->required((bool) ($settings['required'] ?? false));
                    }

                    $desktopColumns = min(4, max(1, count($subjectFields)));
                    $tabletColumns = min(2, $desktopColumns);

                    $gradeFields[] = Fieldset::make($subject['label'] ?? $subject['key'] ?? 'Mata Pelajaran')
                        ->schema($subjectFields)
                        ->columns([
                            'default' => 1,
                            'md' => $tabletColumns,
                            'xl' => $desktopColumns,
                        ]);
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
            $certificateMode = $configuration->achievementCertificateMode();

            $achievementFields = [
                Hidden::make('uuid'),
                TextInput::make('title')->label('Nama Prestasi')->required()->maxLength(200)->columnSpan(['default' => 1, 'md' => 2]),
                Select::make('level')->label('Tingkat')->options($levels)->required(),
                TextInput::make('year')->label('Tahun')->numeric()->minValue(1900)->maxValue(now()->year + 1)->visible((bool) ($settings['show_year'] ?? false)),
                TextInput::make('organizer')->label('Penyelenggara')->maxLength(200)->visible((bool) ($settings['show_organizer'] ?? false)),
                Textarea::make('description')->label('Keterangan')->rows(2)->maxLength(2000)->columnSpanFull()->visible((bool) ($settings['show_description'] ?? false)),
            ];

            if ($certificateMode !== UnitConfiguration::ACHIEVEMENT_CERTIFICATE_MODE_NONE) {
                $achievementFields[] = Hidden::make('certificate_original_name');
                // A nested Action with a $record-dependent visibility callback can be
                // evaluated while FileUpload refreshes the repeater, before its Action
                // container has been initialized. Render an ordinary authenticated link
                // instead: the existing private-file viewer intercepts this URL.
                $achievementFields[] = Placeholder::make('saved_certificate_link')
                    ->label('')
                    ->content(function (?Registration $record, Get $get): HtmlString {
                        $uuid = $get('uuid');

                        if (! $record || ! is_string($uuid) || $uuid === '') {
                            return new HtmlString('');
                        }

                        $achievement = $record->achievements()
                            ->where('uuid', $uuid)
                            ->whereNotNull('certificate_path')
                            ->first();

                        if (! $achievement) {
                            return new HtmlString('');
                        }

                        $url = e(route('files.applicant.achievements.certificate', $achievement));

                        return new HtmlString(
                            '<a href="'.$url.'" class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 underline hover:text-primary-500 dark:text-primary-400">'
                            .'Lihat Bukti Prestasi Tersimpan</a>'
                        );
                    })
                    ->columnSpanFull();
                $achievementFields[] = FileUpload::make('certificate_path')
                    ->label('Sertifikat / Bukti Prestasi')
                    ->required(fn (?Registration $record, Get $get): bool =>
                        $certificateMode === UnitConfiguration::ACHIEVEMENT_CERTIFICATE_MODE_REQUIRED
                        && ! ($record && filled($get('uuid')) && $record->achievements()
                            ->where('uuid', $get('uuid'))
                            ->whereNotNull('certificate_path')
                            ->exists())
                    )
                    ->helperText($certificateMode === UnitConfiguration::ACHIEVEMENT_CERTIFICATE_MODE_REQUIRED
                        ? 'Wajib. File yang tampil baru berhasil diunggah, belum dinyatakan aman. Pemeriksaan keamanan dilakukan saat mengirim/menyimpan formulir. Bukti yang sudah tersimpan tetap berlaku saat revisi jika tidak diganti.'
                        : 'Opsional. File yang tampil baru berhasil diunggah, belum dinyatakan aman. Pemeriksaan keamanan dilakukan saat mengirim/menyimpan formulir.')
                    ->disk(ApplicantFileStorage::PRIVATE_DISK)
                    ->directory(fn (): string => 'pre-registration/'.auth()->id().'/achievements')
                    ->visibility('private')
                    ->previewable(false)
                    ->fetchFileInformation(false)
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize((int) config('spmb.uploads.max_kb', 5120))
                    ->storeFileNamesIn('certificate_original_name')
                    ->getUploadedFileUsing(
                        fn (string $file, string|array|null $storedFileNames): ?array => app(PendingAchievementUpload::class)
                            ->uploadedFile($file, $storedFileNames)
                    )
                    ->columnSpanFull();
            }

            $components['achievements'] = Section::make('Prestasi yang Pernah Diraih')
                ->description('Tambahkan prestasi yang relevan dengan jalur pendaftaran.')
                ->schema([
                    Repeater::make('achievements')
                        ->label('Prestasi')
                        ->default([])
                        ->maxItems((int) ($settings['max_entries'] ?? 3))
                        ->minItems((bool) ($settings['required'] ?? false) ? 1 : 0)
                        ->schema($achievementFields)
                        ->columns(['default' => 1, 'md' => 2])
                        ->itemLabel(fn (array $state): string => $state['title'] ?? 'Prestasi'),
                ])
                ->visible(fn ($get): bool => app(RegistrationSupplementalDataService::class)->featureApplies(
                    true,
                    $settings,
                    $get('registration_pathway_uuid'),
                ));
        }

        $identityLookup = $components['identity_lookup'] ?? null;
        unset($components['identity_lookup']);

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

        if ($identityLookup) {
            array_unshift($ordered, $identityLookup);
        }

        return $ordered;
    }

    /**
     * Keep workflow/validation pinned to the registration configuration while
     * allowing safe applicant-facing metadata to follow the latest published
     * version for the same stable field key.
     *
     * @return list<array<string, mixed>>
     */
    public function presentationFields(UnitConfiguration $configuration): array
    {
        $fields = is_array($configuration->fields) ? $configuration->fields : [];

        if ($configuration->status !== 'published') {
            return $fields;
        }

        $current = app(UnitConfigurationService::class)->current((int) $configuration->unit_id);

        if (! $current || $current->id === $configuration->id) {
            return $fields;
        }

        $latestByKey = collect($current->fields ?? [])->keyBy('key');

        return collect($fields)
            ->map(function (array $field) use ($latestByKey): array {
                $latest = $latestByKey->get($field['key'] ?? null);

                if (! is_array($latest) || ($latest['type'] ?? null) !== ($field['type'] ?? null)) {
                    return $field;
                }

                foreach (['label', 'help', 'template_path', 'placeholder_content'] as $key) {
                    if (array_key_exists($key, $latest)) {
                        $field[$key] = $latest[$key];
                    }
                }

                return $field;
            })
            ->values()
            ->all();
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

    /**
     * Apply the same pinned field version to the staff edit form and the
     * applicant form. No custom answers or relationship keys are rebuilt.
     *
     * @param array<int|string, mixed> $components
     * @return array<int|string, mixed>
     */
    public function applyBuiltinOverridesToSchema(array $components, ?UnitConfiguration $configuration): array
    {
        if (! $configuration) {
            return $components;
        }

        $definitions = collect($this->presentationFields($configuration))->keyBy('key');

        foreach ($components as $index => $item) {
            if ($item instanceof Field && in_array($item->getName(), self::BUILTIN_FIELDS, true)) {
                $name = $item->getName();
                $definition = $definitions->get($name);

                if ($definition) {
                    $item = $this->configuredBuiltinComponent($item, $definition);
                }

                $state = $this->builtinFieldState($configuration, $name);

                if ($state['active'] !== null) {
                    $item->visible($state['active']);
                }

                if ($state['required'] !== null) {
                    $item->required($state['required']);
                }
            }

            // Plain fields/placeholders/actions have no initialized child
            // container while the form schema is being built. Traverse only
            // known layout components that actually own a nested schema.
            if ($item instanceof Section || $item instanceof Fieldset || $item instanceof Group) {
                $children = $item->getChildComponents();

                if ($children) {
                    $item->schema($this->applyBuiltinOverridesToSchema($children, $configuration));
                }
            }

            $components[$index] = $item;
        }

        return $components;
    }

    private function configuredBuiltinComponent(Field $original, array $definition): Field
    {
        $name = $original->getName();
        $type = (string) ($definition['type'] ?? '');

        // Region selectors must keep their cascading lookup callbacks.
        // Other invalid historic field types remain on their native control.
        if (in_array($name, self::REGION_FIELDS, true)
            || ! in_array($type, ['text', 'textarea', 'number', 'date', 'select', 'boolean'], true)
            || ($type === 'select' && empty($definition['options']))) {
            return $original->label($definition['label'])->helperText($definition['help'] ?? null);
        }

        $field = $this->configuredBuiltinField($name, $definition);

        // Preserve identity and database-sized constraints when an existing
        // built-in field changes its presentation component.
        $maxLengths = [
            'nisn' => 10, 'nickname' => 50, 'religion' => 50,
            'phone' => 20, 'email' => 100, 'rt' => 5, 'rw' => 5,
            'previous_school' => 150,
            'father_name' => 150, 'mother_name' => 150,
            'father_nik' => 16, 'mother_nik' => 16,
            'father_birth_place' => 100, 'mother_birth_place' => 100,
            'father_education' => 50, 'mother_education' => 50,
            'father_occupation' => 100, 'mother_occupation' => 100,
            'father_workplace' => 150, 'mother_workplace' => 150,
            'father_phone' => 20, 'mother_phone' => 20,
            'father_email' => 100, 'mother_email' => 100,
            'father_income' => 100, 'mother_income' => 100,
        ];

        if ($field instanceof TextInput) {
            $field->maxLength($maxLengths[$name] ?? 100);
        }

        if (in_array($name, ['nisn', 'father_nik', 'mother_nik'], true)) {
            $field->rule($name === 'nisn' ? 'digits:10' : 'digits:16');
        }

        if ($field instanceof DatePicker && in_array($name, ['father_birth_date', 'mother_birth_date'], true)) {
            $field->maxDate(now()->subDay());
        }

        return $field;
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

    /**
     * Only known, non-file custom inputs are editable in the staff registration
     * form. File answers and unknown legacy types remain read-only so an
     * unrelated edit cannot overwrite private documents or reinterpret values.
     *
     * @return list<array<string, mixed>>
     */
    public function staffEditableCustomDefinitions(UnitConfiguration $configuration): array
    {
        return collect($configuration->fields ?? [])
            ->filter(static fn (mixed $definition): bool => is_array($definition)
                && (bool) ($definition['active'] ?? false)
                && filled($definition['key'] ?? null)
                && ! in_array($definition['key'], self::BUILTIN_FIELDS, true)
                && in_array($definition['type'] ?? null, [
                    'text', 'textarea', 'number', 'date', 'select', 'multiselect', 'boolean',
                ], true))
            ->values()
            ->all();
    }

    /**
     * Render controls from the pinned registration configuration, not the
     * latest published version. In particular, a previous single-choice
     * answer must never turn into a multi-choice editor.
     *
     * @return array<int, Field|Group|Placeholder>
     */
    public function staffEditableCustomFields(Registration $record): array
    {
        $configuration = $record->configuration;

        if (! $configuration) {
            return [];
        }

        return array_map(
            fn (array $definition): Field|Group|Placeholder => $this->field($definition, $configuration),
            $this->staffEditableCustomDefinitions($configuration),
        );
    }

    /**
     * The applicant download route only serves the current published
     * configuration. Pinned forms can use its updated presentation template
     * when the field key, type and template path still match.
     */
    private function templateConfiguration(array $definition, UnitConfiguration $configuration): UnitConfiguration
    {
        if ($configuration->status !== 'published') {
            return $configuration;
        }

        $current = app(UnitConfigurationService::class)->current((int) $configuration->unit_id);
        $latest = collect($current?->fields ?? [])->firstWhere('key', $definition['key']);

        if ($current && is_array($latest)
            && ($latest['type'] ?? null) === ($definition['type'] ?? null)
            && ($latest['template_path'] ?? null) === ($definition['template_path'] ?? null)) {
            return $current;
        }

        return $configuration;
    }

    public function field(array $definition, UnitConfiguration $configuration): Field|Group|Placeholder
    {
        $name = 'custom_answers.'.$definition['key'];

        if (($definition['type'] ?? null) === 'placeholder') {
            $content = app(RegistrationConsentService::class)->sanitizeHtml(
                (string) ($definition['placeholder_content'] ?? ''),
            );

            return Placeholder::make('placeholder_'.$definition['key'])
                ->label('')
                ->content(view('registration.custom-placeholder', [
                    'content' => $content,
                    'downloadUrl' => ! empty($definition['template_path'])
                        ? route('registration.form-field-template', [$this->templateConfiguration($definition, $configuration), $definition['key']])
                        : null,
                    'downloadLabel' => $definition['label'] ?? 'Dokumen',
                ]))
                ->columnSpanFull();
        }

        if (($definition['type'] ?? null) === 'file') {
            $formats = array_values($definition['formats'] ?? ['pdf', 'jpg', 'png']);
            $mimes = array_map(fn (string $format): string => match ($format) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'png' => 'image/png',
                default => 'image/jpeg',
            }, $formats);

            $upload = FileUpload::make($name)
                ->label($definition['label'])
                ->helperText($definition['help'] ?? null)
                ->disk(ApplicantFileStorage::PRIVATE_DISK)
                ->directory(fn (): string => 'pre-registration/'.auth()->id())
                ->visibility('private')
                ->previewable(false)
                ->fetchFileInformation(false)
                ->acceptedFileTypes($mimes)
                ->maxSize((int) config('spmb.uploads.max_kb', 5120))
                ->required(fn (?Registration $record): bool => (bool) $definition['required']
                    && blank(data_get($record?->custom_answers, $definition['key'])));

            $components = [$upload];
            $actions = [];

            if (! empty($definition['template_path'])) {
                $templateConfiguration = $this->templateConfiguration($definition, $configuration);

                $actions[] = Action::make('download_template_'.$definition['key'])
                    ->label('Unduh Template '.$definition['label'])
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(route('registration.form-field-template', [$templateConfiguration, $definition['key']]));
            }

            $actions[] = Action::make('download_existing_'.$definition['key'])
                ->label('Unduh Dokumen Tersimpan')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (?Registration $record): ?string => $record
                    ? route('files.applicant.registration-custom-field', [
                        'registration' => $record,
                        'key' => $definition['key'],
                        'download' => 1,
                    ])
                    : null)
                ->visible(fn (?Registration $record): bool => (bool) ($record
                    && filled(data_get($record->custom_answers, $definition['key']))));

            $components[] = Actions::make($actions);

            return Group::make($components);
        }

        if (($definition['type'] ?? null) === 'boolean') {
            $detailName = 'custom_answers._details.'.$definition['key'];

            $choice = Select::make($name)
                ->label($definition['label'])
                ->helperText($definition['help'] ?? null)
                ->options(['1' => 'Ya', '0' => 'Tidak'])
                ->native(false)
                ->live()
                ->required((bool) $definition['required'])
                ->afterStateUpdated(function (mixed $state, \Filament\Forms\Set $set) use ($detailName): void {
                    $set($detailName, null);
                });

            $detail = Textarea::make($detailName)
                ->label(function (\Filament\Forms\Get $get) use ($definition, $name): string {
                    return $this->booleanDetailSettings($definition, $get($name))['label'] ?? 'Keterangan';
                })
                ->rows(3)
                ->maxLength(5000)
                ->visible(function (\Filament\Forms\Get $get) use ($definition, $name): bool {
                    return $this->booleanDetailSettings($definition, $get($name)) !== null;
                })
                ->required(function (\Filament\Forms\Get $get) use ($definition, $name): bool {
                    return (bool) ($this->booleanDetailSettings($definition, $get($name))['required'] ?? false);
                })
                ->dehydratedWhenHidden(false);

            return Group::make([$choice, $detail])
                ->columns(1);
        }

        $options = array_combine($definition['options'] ?? [], $definition['options'] ?? []);
        $field = match ($definition['type']) {
            'textarea' => Textarea::make($name)->maxLength(5000),
            'number' => TextInput::make($name)->numeric(),
            'date' => DatePicker::make($name),
            'select', 'multiselect' => Select::make($name)->options($options)->multiple($definition['type'] === 'multiselect'),
            default => TextInput::make($name)->maxLength(1000),
        };

        return $field->label($definition['label'])->helperText($definition['help'] ?? null)->required((bool) $definition['required']);
    }

    /**
     * @return array{label:string,required:bool}|null
     */
    public function booleanDetailSettings(array $definition, mixed $answer): ?array
    {
        $branch = match (true) {
            in_array($answer, [true, 1, '1'], true) => 'yes',
            in_array($answer, [false, 0, '0'], true) => 'no',
            default => null,
        };

        if (! $branch || ! (bool) ($definition['boolean_'.$branch.'_detail_enabled'] ?? false)) {
            return null;
        }

        return [
            'label' => filled($definition['boolean_'.$branch.'_detail_label'] ?? null)
                ? trim((string) $definition['boolean_'.$branch.'_detail_label'])
                : 'Keterangan',
            'required' => (bool) ($definition['boolean_'.$branch.'_detail_required'] ?? false),
        ];
    }

    public function validateAnswers(?UnitConfiguration $configuration, array $answers): array
    {
        if (! $configuration) {
            return [];
        }

        $rules = [];
        $attributes = [];
        $fileAnswers = [];

        foreach ($configuration->fields as $field) {
            if (! $field['active']
                || in_array($field['key'], self::BUILTIN_FIELDS, true)
                || ($field['type'] ?? null) === 'placeholder') {
                continue;
            }

            $name = $field['key'];

            if (($field['type'] ?? null) === 'file') {
                $value = $answers[$name] ?? null;

                if (blank($value)) {
                    if ($field['required']) {
                        throw ValidationException::withMessages([
                            'custom_answers.'.$name => $field['label'].' wajib diunggah.',
                        ]);
                    }

                    continue;
                }

                if (! is_string($value)
                    || ! str_starts_with($value, 'pre-registration/'.auth()->id().'/')) {
                    throw ValidationException::withMessages([
                        'custom_answers.'.$name => 'Lokasi file unggahan tidak valid.',
                    ]);
                }

                $formats = array_values($field['formats'] ?? ['pdf', 'jpg', 'png']);

                try {
                    app(ApplicantUploadSecurity::class)->inspect($value, $formats);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'custom_answers.'.$name => collect($exception->errors())->flatten()->first()
                            ?: 'File unggahan tidak lolos pemeriksaan keamanan.',
                    ]);
                }

                $fileAnswers[$name] = $value;

                continue;
            }

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

            $attributes[$name] = (string) $field['label'];

            if ($field['type'] === 'boolean') {
                $settings = $this->booleanDetailSettings($field, $answers[$name] ?? null);

                if ($settings) {
                    $detailRule = '_details.'.$name;
                    $rules[$detailRule] = [
                        $settings['required'] ? 'required' : 'nullable',
                        'string',
                        'max:5000',
                    ];
                    $attributes[$detailRule] = $settings['label'];
                }
            }
        }

        return array_merge(
            Validator::make($answers, $rules, [], $attributes)->validate(),
            $fileAnswers,
        );
    }
}
