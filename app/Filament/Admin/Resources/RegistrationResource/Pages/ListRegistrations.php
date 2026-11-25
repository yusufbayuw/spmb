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

    public function updatedTableFilters(): void
    {
        if (\App\Support\SpmbOperationalMode::isHigherEducation()) {
            $oldNames = array_keys($this->getTable()->getFilters());
            $oldCustom = array_values(array_filter($oldNames, fn (string $name): bool => str_starts_with($name, 'custom_')));
            // Filament builds table definitions before Livewire applies updates.
            // Rebuild after a unit change, dropping incompatible filter state.
            $service = app(\App\Services\HigherEducationRegistrationTableService::class);
            $unit = $service->activeUnit(app(\App\Services\RegistrationTableColumnService::class)->activeUnit($this->getTable()));
            $newCustom = array_map(fn ($filter) => $filter->getName(), $service->customFilters($unit));

            if ($oldCustom !== $newCustom) {
                foreach (array_keys($this->tableFilters ?? []) as $name) {
                    if (str_starts_with($name, 'custom_') && ! in_array($name, $newCustom, true)) {
                        unset($this->tableFilters[$name]);
                    }
                }
                if (str_starts_with($this->tableSortColumn ?? '', 'custom:')) {
                    $this->tableSortColumn = null;
                    $this->tableSortDirection = null;
                    $this->updatedTableSortColumn();
                }
                foreach (array_keys($this->toggledTableColumns) as $name) {
                    if (str_starts_with($name, 'custom:')) {
                        unset($this->toggledTableColumns[$name]);
                    }
                }

                $this->isCachingForms = true;
                $this->bootedInteractsWithTable();
                $this->cacheForms();
                $this->isCachingForms = false;
                $this->getTableColumnToggleForm()->fill(array_replace_recursive(
                    $this->getDefaultTableColumnToggleState(), $this->toggledTableColumns
                ));
                $this->getTableFiltersForm()->fill($this->tableFilters);
            }
        }

        parent::updatedTableFilters();
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
