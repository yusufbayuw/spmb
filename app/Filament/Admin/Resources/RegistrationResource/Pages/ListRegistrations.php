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
            'active' => Tab::make('Aktif')->query(fn (Builder $query): Builder => $query->where('lifecycle_status', 'active')),
            'withdrawn' => Tab::make('Mengundurkan Diri')->query(fn (Builder $query): Builder => $query->where('lifecycle_status', 'withdrawn')),
            'cancelled' => Tab::make('Dibatalkan')->query(fn (Builder $query): Builder => $query->where('lifecycle_status', 'cancelled')),
            'archived' => Tab::make('Diarsipkan')->query(fn (Builder $query): Builder => $query->where('lifecycle_status', 'archived')),
        ];
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
