<?php

namespace App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;

use App\Filament\Admin\Resources\RegistrationOpeningResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRegistrationOpenings extends ListRecords
{
    protected static string $resource = RegistrationOpeningResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'draft' => Tab::make('Draft')->query(fn (Builder $query): Builder => $query
                ->where('status', 'draft')
                ->where(function (Builder $schedule): void {
                    $schedule->whereNull('opened_at')->orWhereNull('closed_at');
                })),
            'scheduled' => Tab::make('Dijadwalkan')->query(fn (Builder $query): Builder => $query
                ->where('status', '!=', 'archived')
                ->whereNotNull('opened_at')
                ->where('opened_at', '>', now())),
            'open' => Tab::make('Dibuka')->query(fn (Builder $query): Builder => $query->currentlyOpen()),
            'closed' => Tab::make('Ditutup')->query(fn (Builder $query): Builder => $query
                ->where('status', '!=', 'archived')
                ->where(function (Builder $closed): void {
                    $closed
                        ->where('closed_at', '<=', now())
                        ->orWhere(function (Builder $legacy): void {
                            $legacy
                                ->where('status', 'closed')
                                ->where(function (Builder $schedule): void {
                                    $schedule->whereNull('opened_at')->orWhereNull('closed_at');
                                });
                        });
                })),
            'archived' => Tab::make('Diarsipkan')->query(fn (Builder $query): Builder => $query
                ->where(function (Builder $archived): void {
                    $archived->where('status', 'archived')->orWhereNotNull('archived_at');
                })),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Buat Pembukaan'),
        ];
    }
}
