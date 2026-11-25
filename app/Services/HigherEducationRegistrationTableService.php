<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Read-only, type-aware query helpers for the HIGHER_EDUCATION registration table.
 * SQL identifiers come only from fixed metadata or validated configuration keys.
 * All filter values are passed as bound parameters.
 */
class HigherEducationRegistrationTableService
{
    private const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'multiselect', 'boolean', 'file'];

    public function accessibleUnits(): Builder
    {
        $actor = auth()->user();

        return Unit::query()->operational()->where('institution_type', 'university')
            ->when(! $actor?->is_active || ! ($actor?->isAdmin() || $actor?->isTU()), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($actor?->isTU(), fn (Builder $query) => $query->whereKey($actor->unit_id));
    }

    public function activeUnitForTable(Table $table): ?Unit
    {
        $query = $this->accessibleUnits();
        $actor = auth()->user();

        if ($actor?->isTU()) {
            return $query->first();
        }

        $selected = data_get($table->getLivewire()->tableFilters, 'unit_id.value');
        if ($selected !== null && $selected !== '') {
            if ((! is_string($selected) && ! is_int($selected))
                || ! preg_match('/^[1-9][0-9]*$/D', (string) $selected)) {
                return null;
            }

            return $query->whereKey($selected)->first();
        }

        $units = $query->limit(2)->get();

        return $units->count() === 1 ? $units->first() : null;
    }

    public function validCustomDefinition(array $definition): bool
    {
        return is_string($definition['key'] ?? null)
            && preg_match('/^[a-z][a-z0-9_]{0,59}$/D', $definition['key']) === 1
            && in_array($definition['type'] ?? 'text', self::TYPES, true);
    }

    private function driver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }

    /** Untrusted custom keys never become arbitrary SQL identifiers or JSON paths. */
    private function jsonParts(Builder $query, string $key): array
    {
        if (! $this->validCustomDefinition(['key' => $key])) {
            throw new InvalidArgumentException('Invalid registration field key.');
        }

        $path = "'$.".$key."'";
        $json = "JSON_EXTRACT(registrations.custom_answers, {$path})";

        if ($this->driver($query) === 'sqlite') {
            return ["LOWER(JSON_TYPE(registrations.custom_answers, {$path}))", "CAST({$json} AS TEXT)"];
        }

        return ["LOWER(JSON_TYPE({$json}))", "JSON_UNQUOTE({$json})"];
    }

    public function customPresence(Builder $query, string $key): string
    {
        [$kind, $raw] = $this->jsonParts($query, $key);

        return "(CASE WHEN {$kind} IS NULL OR {$kind} = 'null' THEN NULL
            WHEN {$kind} IN ('text', 'string') AND TRIM({$raw}) = '' THEN NULL
            WHEN {$kind} = 'array' AND {$raw} = '[]' THEN NULL
            WHEN {$kind} = 'object' AND {$raw} = '{}' THEN NULL
            ELSE {$raw} END)";
    }

    public function customValue(Builder $query, string $key, string $type): string
    {
        [$kind, $raw] = $this->jsonParts($query, $key);
        $present = $this->customPresence($query, $key);
        $sqlite = $this->driver($query) === 'sqlite';
        $trim = "TRIM({$raw})";
        $scalar = "{$kind} IN ('text', 'string', 'integer', 'real', 'double', 'decimal')";
        $dateShape = $sqlite
            ? "{$trim} GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]'"
            : "{$trim} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'";

        if ($type === 'number') {
            if ($sqlite) {
                // JSON numbers (including exponent notation), plus conventional decimal
                // strings with a leading sign or zero. Reject malformed historic text.
                $unsigned = "(CASE WHEN SUBSTR({$trim}, 1, 1) IN ('+', '-') THEN SUBSTR({$trim}, 2) ELSE {$trim} END)";
                $decimal = "({$unsigned} GLOB '*[0-9]*' AND {$unsigned} NOT GLOB '*[^0-9.]*'
                    AND LENGTH({$unsigned}) - LENGTH(REPLACE({$unsigned}, '.', '')) <= 1)";
                $valid = "(CASE WHEN JSON_VALID({$trim}) THEN JSON_TYPE({$trim}) IN ('integer', 'real') ELSE 0 END OR {$decimal})";
                return "(CASE WHEN {$scalar} AND {$valid} THEN CAST({$trim} AS REAL) ELSE NULL END)";
            }

            return "(CASE WHEN {$scalar} AND {$trim} REGEXP '^[+-]?([0-9]+([.][0-9]*)?|[.][0-9]+)([eE][+-]?[0-9]+)?$'
                THEN ({$trim} + 0.0) ELSE NULL END)";
        }

        return match ($type) {
            'date' => "(CASE WHEN {$scalar} AND {$dateShape} THEN {$trim} ELSE NULL END)",
            'boolean' => "(CASE WHEN {$kind} IN ('true', 'false', 'boolean', 'text', 'string', 'integer', 'real', 'double')
                THEN CASE WHEN LOWER({$trim}) IN ('true', '1') THEN 1 WHEN LOWER({$trim}) IN ('false', '0') THEN 0 ELSE NULL END ELSE NULL END)",
            'file' => "(CASE WHEN {$present} IS NULL THEN 0 ELSE 1 END)",
            'multiselect' => "(CASE WHEN {$kind} = 'array' THEN {$present} ELSE NULL END)",
            default => "(CASE WHEN {$scalar} THEN {$present} ELSE NULL END)",
        };
    }

    private function sortExpression(Builder $query, string $expression, string $direction): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw("CASE WHEN {$expression} IS NULL THEN 1 ELSE 0 END")
            ->orderByRaw("{$expression} {$direction}");
    }

    public function sortCustom(Builder $query, int $unitId, string $key, string $type, string $direction): Builder
    {
        $expression = $this->customValue($query, $key, $type);
        // The unit is a server-resolved integer; data from any other unit cannot
        // affect the custom order even during a filter/unit transition.
        $expression = "(CASE WHEN registrations.unit_id = ".(int) $unitId." THEN {$expression} ELSE NULL END)";
        if (in_array($type, ['text', 'textarea', 'select', 'multiselect'], true)) {
            $expression = "LOWER({$expression})";
        }

        return $this->sortExpression($query, $expression, $direction);
    }

    private function openingValue(string $column): string
    {
        // Only called with the fixed column names in builtinFilters().
        return "(SELECT registration_openings.{$column} FROM registration_openings
            WHERE registration_openings.id = registrations.registration_opening_id)";
    }

    private function programLabel(Builder $query): string
    {
        $level = "COALESCE(education_levels.code, study_programs.degree_level, '')";
        $label = $this->driver($query) === 'sqlite'
            ? "TRIM({$level} || ' ' || study_programs.name)"
            : "TRIM(CONCAT({$level}, ' ', study_programs.name))";

        return "(SELECT {$label} FROM registration_openings
            JOIN study_programs ON study_programs.id = registration_openings.study_program_id
            LEFT JOIN education_levels ON education_levels.id = study_programs.education_level_id
            WHERE registration_openings.id = registrations.registration_opening_id
            AND study_programs.unit_id = registrations.unit_id)";
    }

    private function orderedStatus(Builder $query, string $column, array $values, string $direction): Builder
    {
        $cases = [];
        foreach (array_values($values) as $index => $value) {
            $cases[] = 'WHEN ? THEN '.($index + 1);
        }

        // Unknown historic states sort last, independent of direction.
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw("CASE WHEN {$column} IN ({$placeholders}) THEN 0 ELSE 1 END", array_values($values))
            ->orderByRaw("CASE {$column} ".implode(' ', $cases)." ELSE 0 END {$direction}", array_values($values));
    }

    public function configureBuiltinSort(TextColumn $column): TextColumn
    {
        return match ($column->getName()) {
            'study_program' => $column->sortable(query: fn (Builder $query, string $direction) =>
                $this->sortExpression($query, 'LOWER('.$this->programLabel($query).')', $direction)),
            'current_stage' => $column->sortable(query: fn (Builder $query, string $direction) =>
                $this->orderedStatus($query, 'registrations.current_stage', array_keys(Registration::STAGES), $direction)),
            'lifecycle_status' => $column->sortable(query: fn (Builder $query, string $direction) =>
                $this->orderedStatus($query, 'registrations.lifecycle_status', array_keys(Registration::LIFECYCLE_STATUSES), $direction)),
            'data_validation_status' => $column->sortable(query: fn (Builder $query, string $direction) =>
                $this->orderedStatus($query, 'registrations.data_validation_status', ['pending', 'revision', 'valid'], $direction)),
            default => $column,
        };
    }

    private function scalar(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private function selections(mixed $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $value): ?string => $this->scalar($value),
            is_array($values) ? $values : [],
        ), fn ($value) => $value !== null)));
    }

    private function hasCriteria(array $data): bool
    {
        return in_array($data['presence'] ?? null, ['filled', 'empty'], true)
            || $this->scalar($data['value'] ?? null) !== null
            || $this->scalar($data['from'] ?? null) !== null
            || $this->scalar($data['to'] ?? null) !== null
            || $this->selections($data['values'] ?? []) !== [];
    }

    private function applyPresence(Builder $query, string $presence, array $data): void
    {
        if (($data['presence'] ?? null) === 'filled') {
            $query->whereRaw("{$presence} IS NOT NULL");
        } elseif (($data['presence'] ?? null) === 'empty') {
            $query->whereRaw("{$presence} IS NULL");
        }
    }

    private function validDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }

    private function applyValue(Builder $query, string $expression, string $type, array $data): void
    {
        if (in_array($type, ['number', 'date', 'datetime'], true)) {
            foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
                $value = $this->scalar($data[$field] ?? null);
                if ($value === null) {
                    continue;
                }
                if ($type === 'number' && is_numeric($value) && is_finite((float) $value)) {
                    // SQLite expressions have no column affinity; cast the bound
                    // numeric parameter so string-bound PDO values compare numerically.
                    $cast = $this->driver($query) === 'sqlite' ? 'CAST(? AS REAL)' : '(? + 0.0)';
                    $query->whereRaw("{$expression} {$operator} {$cast}", [$value]);
                } elseif ($type !== 'number' && $this->validDate($value)) {
                    if ($type === 'datetime') {
                        $value .= $field === 'from' ? ' 00:00:00' : ' 23:59:59';
                    }
                    $query->whereRaw("{$expression} {$operator} ?", [$value]);
                }
            }

            return;
        }

        if ($type === 'select' || $type === 'boolean') {
            $values = $this->selections($data['values'] ?? []);
            if ($values !== []) {
                if ($type === 'boolean') {
                    $values = array_values(array_intersect($values, ['0', '1']));
                    if ($values === []) {
                        return;
                    }
                    $values = array_map('intval', $values);
                }
                $query->whereIn(DB::raw($expression), $values);
            }

            return;
        }

        if (! in_array($type, ['text', 'textarea'], true)) {
            return;
        }

        $value = $this->scalar($data['value'] ?? null);
        if ($value === null) {
            return;
        }

        if (($data['operator'] ?? null) === 'equals') {
            $query->whereRaw("LOWER({$expression}) = LOWER(?)", [$value]);
        } else {
            $value = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
            $query->whereRaw("LOWER({$expression}) LIKE LOWER(?) ESCAPE '!'", ['%'.$value.'%']);
        }
    }

    public function filterCustom(Builder $query, int $unitId, string $key, string $type, array $data): Builder
    {
        if (! $this->hasCriteria($data)) {
            return $query;
        }

        $query->where('registrations.unit_id', $unitId);
        $this->applyPresence($query, $this->customPresence($query, $key), $data);

        if ($type === 'multiselect') {
            $values = $this->selections($data['values'] ?? []);
            if ($values !== []) {
                [$kind] = $this->jsonParts($query, $key);
                $query->whereRaw("{$kind} = 'array'");
                $query->where(function (Builder $nested) use ($key, $values, $data): void {
                    foreach ($values as $value) {
                        if (($data['match'] ?? null) === 'all') {
                            $nested->whereJsonContains('registrations.custom_answers->'.$key, $value);
                        } else {
                            $nested->orWhereJsonContains('registrations.custom_answers->'.$key, $value);
                        }
                    }
                });
            }
        } else {
            $this->applyValue($query, $this->customValue($query, $key, $type), $type, $data);
        }

        return $query;
    }

    private function filter(string $name, string $label, string $type, callable $apply, array $options = []): Filter
    {
        $form = match ($type) {
            'number' => [
                TextInput::make('from')->label($label.' — minimum')->numeric(),
                TextInput::make('to')->label($label.' — maksimum')->numeric(),
            ],
            'date', 'datetime' => [
                DatePicker::make('from')->label($label.' — dari')->native(false),
                DatePicker::make('to')->label($label.' — sampai')->native(false),
            ],
            'select', 'boolean', 'multiselect' => [
                Select::make('values')->label($label)->options($options)->multiple()->searchable(),
                ...($type === 'multiselect' ? [
                    Select::make('match')->label('Pencocokan '.$label)->options(['any' => 'Salah satu', 'all' => 'Semua pilihan'])->default('any'),
                ] : []),
            ],
            'file' => [],
            default => [
                TextInput::make('value')->label($label)->maxLength(1000),
                Select::make('operator')->label('Pencocokan '.$label)->options(['contains' => 'Mengandung', 'equals' => 'Sama dengan'])->default('contains'),
            ],
        };
        $form[] = Select::make('presence')->label('Isian '.$label)
            ->options($type === 'file' ? ['filled' => 'Sudah diunggah', 'empty' => 'Belum diunggah'] : ['filled' => 'Sudah diisi', 'empty' => 'Belum diisi'])
            ->placeholder('Semua');

        return Filter::make($name)->label($label)->form($form)
            ->query(fn (Builder $query, array $data): Builder => $apply($query, $data))
            ->indicateUsing(fn (array $data): ?string => $this->hasCriteria($data) ? $label.': filter aktif' : null);
    }

    private function builtin(string $name, string $label, string $type, string $expression, array $options = []): Filter
    {
        return $this->filter($name, $label, $type, function (Builder $query, array $data) use ($type, $expression): Builder {
            if (! $this->hasCriteria($data)) {
                return $query;
            }
            $this->applyPresence($query, "NULLIF(TRIM({$expression}), '')", $data);
            $this->applyValue($query, $expression, $type, $data);

            return $query;
        }, $options);
    }

    public function builtinFilters(?Unit $unit): array
    {
        $unitIds = $this->accessibleUnits()->when($unit, fn (Builder $query) => $query->whereKey($unit->id))->select('units.id');
        $openings = RegistrationOpening::query()->whereIn('unit_id', clone $unitIds);
        $programs = StudyProgram::query()->whereIn('unit_id', clone $unitIds)->with('educationLevel')
            ->orderBy('name')->get()->mapWithKeys(fn (StudyProgram $program) => [$program->id => $program->label()])->all();
        $pathways = RegistrationPathway::query()->whereIn('unit_id', clone $unitIds)->orderBy('name')->pluck('name', 'id')->all();
        $years = (clone $openings)->select('academic_year')->distinct()->orderBy('academic_year')->pluck('academic_year', 'academic_year')->all();
        $waves = (clone $openings)->select('wave')->distinct()->orderBy('wave')->pluck('wave', 'wave')->all();

        $filters = [];
        foreach (['full_name', 'nik', 'registration_number', 'nisn', 'nickname', 'birth_place', 'phone', 'email', 'previous_school'] as $key) {
            $filters[] = $this->builtin($key, RegistrationTableColumnService::LABELS[$key], 'text', 'registrations.'.$key);
        }

        return [
            ...$filters,
            $this->builtin('academic_year', 'Tahun Ajaran', 'select', $this->openingValue('academic_year'), $years),
            $this->builtin('wave', 'Gelombang', 'select', $this->openingValue('wave'), $waves),
            $this->builtin('pathway_id', 'Jalur', 'select', 'registrations.registration_pathway_id', $pathways),
            $this->builtin('registration_fee', 'Biaya Formulir', 'number', $this->openingValue('registration_fee')),
            $this->builtin('study_program', 'Program Studi', 'select', $this->openingValue('study_program_id'), $programs),
            $this->builtin('created_at', 'Tanggal Daftar', 'datetime', 'registrations.created_at'),
            $this->builtin('birth_date', 'Tanggal Lahir', 'date', 'registrations.birth_date'),
            $this->builtin('graduation_year', 'Tahun Lulus', 'number', 'registrations.graduation_year'),
            $this->builtin('gender', 'Jenis Kelamin', 'select', 'registrations.gender', ['L' => 'Laki-laki', 'P' => 'Perempuan']),
            $this->builtin('data_validation_status', 'Status Validasi', 'select', 'registrations.data_validation_status', ['pending' => 'Menunggu Validasi', 'revision' => 'Perlu Revisi', 'valid' => 'Valid']),
        ];
    }

    public function customFilters(?Unit $unit): array
    {
        if (! $unit) {
            return [];
        }

        $columns = app(RegistrationTableColumnService::class);
        $visible = $columns->visibleColumns($unit);
        $configuration = app(UnitConfigurationService::class)->current((int) $unit->id);
        $historic = UnitConfiguration::query()->where('unit_id', $unit->id)->where('status', 'published')->pluck('fields');
        $filters = [];
        foreach ($configuration?->fields ?? [] as $definition) {
            if (! is_array($definition) || ! $this->validCustomDefinition($definition)
                || ! in_array('custom:'.$definition['key'], $visible, true)) {
                continue;
            }
            $key = $definition['key'];
            $type = $definition['type'] ?? 'text';
            $label = (string) ($definition['label'] ?? $key);
            $options = [];
            if ($type === 'boolean') {
                $options = ['1' => 'Ya', '0' => 'Tidak'];
            } elseif (in_array($type, ['select', 'multiselect'], true)) {
                $values = $definition['options'] ?? [];
                // Options removed from a later version remain filterable for
                // historical registrations of this unit.
                foreach ($historic as $fields) {
                    foreach (is_array($fields) ? $fields : [] as $old) {
                        if (is_array($old) && ($old['key'] ?? null) === $key && ($old['type'] ?? null) === $type) {
                            $values = array_merge($values, $old['options'] ?? []);
                        }
                    }
                }
                foreach ($values as $value) {
                    if (is_scalar($value)) {
                        $options[(string) $value] = (string) $value;
                    }
                }
            }

            $filters[] = $this->filter('custom_'.$unit->id.'_'.$key, $label, $type,
                fn (Builder $query, array $data): Builder => $this->filterCustom($query, (int) $unit->id, $key, $type, $data),
                $options);
        }

        return $filters;
    }
}
