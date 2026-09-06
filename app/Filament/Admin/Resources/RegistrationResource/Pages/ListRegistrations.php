<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Filament\Admin\Resources\RegistrationResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

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
            Actions\CreateAction::make(),
        ];
    }
}
