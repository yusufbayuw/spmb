<?php

namespace App\Services;

use App\Models\Unit;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RegistrationTableColumnService
{
    public const DEFAULT_COLUMNS = [
        'full_name', 'nik', 'registration_number', 'unit.name',
        'opening.academic_year', 'is_alumni', 'opening.wave',
        'pathway.name', 'opening.registration_fee', 'current_stage',
        'lifecycle_status', 'created_at',
    ];

    public const LABELS = [
        'full_name' => 'Calon Peserta',
        'nik' => 'NIK',
        'registration_number' => 'No. Registrasi',
        'unit.name' => 'Unit',
        'opening.academic_year' => 'Tahun Ajaran',
        'is_alumni' => 'Alumni',
        'opening.wave' => 'Gelombang',
        'pathway.name' => 'Jalur',
        'opening.registration_fee' => 'Biaya Formulir',
        'current_stage' => 'Tahap',
        'lifecycle_status' => 'Lifecycle',
        'created_at' => 'Tanggal Daftar',
        'study_program' => 'Program Studi',
        'nisn' => 'NISN',
        'nickname' => 'Nama Panggilan',
        'gender' => 'Jenis Kelamin',
        'birth_place' => 'Tempat Lahir',
        'birth_date' => 'Tanggal Lahir',
        'phone' => 'Telepon Peserta',
        'email' => 'Email Peserta',
        'previous_school' => 'Sekolah Asal',
        'graduation_year' => 'Tahun Lulus',
        'data_validation_status' => 'Status Validasi',
    ];

    /** @return array<string, string> */
    public function options(Unit $unit): array
    {
        $options = self::LABELS;

        if (! $unit->isHigherEducation()) {
            unset($options['study_program']);
        }

        $configuration = app(UnitConfigurationService::class)->current((int) $unit->id);

        foreach ($configuration?->fields ?? [] as $field) {
            if (! $this->isCustomField($field)) {
                continue;
            }

            $key = (string) $field['key'];
            $options['custom:'.$key] = (string) ($field['label'] ?? $key);
        }

        return $options;
    }

    /**
     * Build custom columns from the unit's PUBLISHED form only.
     *
     * When a super admin views all units, include custom columns enabled by
     * any operational unit. Give them unit-scoped names to avoid collisions
     * between unrelated field keys/labels on different units.
     *
     * @return list<array{name:string,label:string,key:string,type:string,unit_id:int,default_visible:bool}>
     */
    public function customColumns(?Unit $unit): array
    {
        if ($unit) {
            return $this->unitCustomColumns($unit);
        }

        // No unit scope is only available to the super admin. No database
        // access is needed when rendering the table in isolated UI tests.
        if (! auth()->user()?->isAdmin()
            || ! Schema::hasTable('units')
            || ! Schema::hasTable('unit_configurations')) {
            return [];
        }

        $units = Unit::query()->operational()->orderBy('name')->get();
        $hasMultipleUnits = $units->count() > 1;
        $columns = [];

        foreach ($units as $candidate) {
            foreach ($this->unitCustomColumns($candidate) as $column) {
                // In the aggregate view show only fields the unit explicitly
                // enabled, so disabled/new fields cannot clutter every table.
                if (! $column['default_visible']) {
                    continue;
                }

                $column['name'] = 'custom:'.$candidate->id.':'.$column['key'];
                $column['label'] .= $hasMultipleUnits ? ' ('.$candidate->name.')' : '';
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @return list<array{name:string,label:string,key:string,type:string,unit_id:int,default_visible:bool}>
     */
    private function unitCustomColumns(Unit $unit): array
    {
        $configuration = app(UnitConfigurationService::class)->current((int) $unit->id);
        $selected = is_array($unit->registration_table_columns)
            ? $unit->registration_table_columns
            : $this->defaults($unit);
        $columns = [];

        foreach ($configuration?->fields ?? [] as $field) {
            if (! $this->isCustomField($field)) {
                continue;
            }

            $key = (string) $field['key'];
            $columns[] = [
                'name' => 'custom:'.$key,
                'label' => (string) ($field['label'] ?? $key),
                'key' => $key,
                'type' => (string) ($field['type'] ?? 'text'),
                'unit_id' => (int) $unit->id,
                'default_visible' => in_array('custom:'.$key, $selected, true),
            ];
        }

        return $columns;
    }

    private function isCustomField(mixed $field): bool
    {
        return is_array($field)
            && filled($field['key'] ?? null)
            && ! in_array((string) $field['key'], ConfiguredRegistrationForm::BUILTIN_FIELDS, true)
            && (bool) ($field['active'] ?? false)
            && ($field['type'] ?? '') !== 'placeholder';
    }

    /** @return list<string> */
    public function defaults(?Unit $unit = null): array
    {
        return $unit?->isHigherEducation()
            ? [...self::DEFAULT_COLUMNS, 'study_program']
            : self::DEFAULT_COLUMNS;
    }

    /** @return list<string> */
    public function visibleColumns(Unit $unit): array
    {
        $saved = $unit->registration_table_columns;

        if (! is_array($saved)) {
            return $this->defaults($unit);
        }

        return array_values(array_unique([
            'full_name', ...array_values(array_intersect($saved, array_keys($this->options($unit)))),
        ]));
    }

    /** @param list<string> $columns
     *  @return list<string>
     */
    public function validateColumns(Unit $unit, array $columns): array
    {
        if (array_diff($columns, array_keys($this->options($unit))) !== []) {
            throw ValidationException::withMessages([
                'registration_table_columns' => 'Terdapat kolom yang tidak tersedia untuk unit ini.',
            ]);
        }

        return array_values(array_unique(['full_name', ...$columns]));
    }

    public function activeUnit(Table $table): ?Unit
    {
        $user = auth()->user();

        if ($user?->unit_id && ($user->isTU() || $user->isAdminUnit())) {
            return Unit::query()->find($user->unit_id);
        }

        $filters = $table->getLivewire()->tableFilters ?? [];
        $unitId = data_get($filters, 'unit_id.value');

        return filled($unitId) ? Unit::query()->find($unitId) : null;
    }

    public function showByDefault(?Unit $unit, string $key): bool
    {
        if ($unit) {
            return in_array($key, $this->visibleColumns($unit), true);
        }

        if ($key === 'study_program') {
            if (\App\Support\SpmbOperationalMode::isHigherEducation()) {
                return true;
            }

            return \App\Support\SpmbOperationalMode::isMixed()
                && \Illuminate\Support\Facades\Schema::hasTable('units')
                && Unit::query()->operational()->where('institution_type', 'university')->exists();
        }

        return in_array($key, self::DEFAULT_COLUMNS, true);
    }
}
