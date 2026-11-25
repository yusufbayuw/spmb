<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Services\RegistrationExcelExportService;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    /**
     * Request-local snapshot of the unit used to build the current table.
     * Compare actual unit identity rather than filter names: even two units
     * without custom fields may have different programs and pathways.
     */
    protected ?int $tableDefinitionUnitId = null;

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        if (\App\Support\SpmbOperationalMode::isHigherEducation()) {
            $this->tableDefinitionUnitId = app(\App\Services\HigherEducationRegistrationTableService::class)
                ->activeUnitForTable($table)?->id;
        }

        return parent::table($table);
    }

    protected function handleTableFilterUpdates(): void
    {
        if (\App\Support\SpmbOperationalMode::isHigherEducation()) {
            $unitId = app(\App\Services\HigherEducationRegistrationTableService::class)
                ->activeUnitForTable($this->getTable())?->id;

            if ($this->tableDefinitionUnitId !== $unitId) {
                // Discard choices tied to the previous unit, while preserving
                // generic filters such as name, validation status and dates.
                foreach (array_keys($this->tableFilters ?? []) as $name) {
                    if (str_starts_with($name, 'custom_') || in_array($name, [
                        'registration_opening_id', 'study_program', 'pathway_id', 'academic_year', 'wave',
                    ], true)) {
                        unset($this->tableFilters[$name]);
                    }
                }
                if (str_starts_with($this->tableSortColumn ?? '', 'custom:')) {
                    $this->tableSortColumn = null;
                    $this->tableSortDirection = null;
                }

                $this->bootedInteractsWithTable();
                // Resolve forms inside cacheForm's callback, where caching
                // mode is active, instead of reusing the old cached schema.
                $this->cacheForm('toggleTableColumnForm', fn () => $this->getTableColumnToggleForm());
                $this->cacheForm('tableFiltersForm', fn () => $this->getTableFiltersForm());
                $this->getTableColumnToggleForm()->fill($this->getDefaultTableColumnToggleState());
                $this->getTableFiltersForm()->fill($this->tableFilters);
                $this->updatedToggledTableColumns();
                $this->updatedTableSortColumn();
            }
        }

        parent::handleTableFilterUpdates();
        $this->flushCachedTableRecords();
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'validated' => Tab::make('Sudah Validasi')
                ->query(fn (Builder $query): Builder => $query->where('data_validation_status', 'valid')),
            'pending_validation' => Tab::make('Belum Validasi')
                ->query(fn (Builder $query): Builder => static::awaitingValidation($query))
                ->badge(fn (): int => static::awaitingValidation(RegistrationResource::getEloquentQuery())->count())
                ->badgeColor('warning'),
        ];
    }

    /**
     * The actionable validation queue (including requests returned for revision).
     * Keeping this scope shared ensures the tab and badge always agree.
     */
    private static function awaitingValidation(Builder $query): Builder
    {
        return $query
            ->where('lifecycle_status', 'active')
            ->where('current_stage', 'data_validation')
            ->whereIn('data_validation_status', ['pending', 'revision']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->tooltip('Export mengikuti filter, pencarian, dan tab yang sedang aktif.')
                ->action(fn (): BinaryFileResponse => $this->exportExcel()),
            Actions\CreateAction::make(),
        ];
    }

    public function exportExcel(): BinaryFileResponse
    {
        $result = app(RegistrationExcelExportService::class)->export(
            $this->getFilteredTableQuery(),
            auth()->user(),
        );

        return response()
            ->download(
                $result['path'],
                $result['filename'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ],
            )
            ->deleteFileAfterSend(true);
    }
}
