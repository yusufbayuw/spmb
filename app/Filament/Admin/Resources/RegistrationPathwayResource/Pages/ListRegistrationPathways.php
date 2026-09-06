<?php

namespace App\Filament\Admin\Resources\RegistrationPathwayResource\Pages;

use App\Filament\Admin\Resources\RegistrationPathwayResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRegistrationPathways extends ListRecords
{
    protected static string $resource = RegistrationPathwayResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'active' => Tab::make('Aktif')->query(fn (Builder $query): Builder => $query->where('is_active', true)->whereNull('archived_at')),
            'inactive' => Tab::make('Nonaktif')->query(fn (Builder $query): Builder => $query->where('is_active', false)->whereNull('archived_at')),
            'archived' => Tab::make('Diarsipkan')->query(fn (Builder $query): Builder => $query->whereNotNull('archived_at')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
