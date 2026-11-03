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
