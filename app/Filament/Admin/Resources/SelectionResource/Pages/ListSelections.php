<?php

namespace App\Filament\Admin\Resources\SelectionResource\Pages;

use App\Filament\Admin\Resources\SelectionResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSelections extends ListRecords
{
    protected static string $resource = SelectionResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'pending' => Tab::make('Belum Diputuskan')->query(fn (Builder $query): Builder => $query->where('decision', 'pending')),
            'accepted' => Tab::make('Diterima')->query(fn (Builder $query): Builder => $query->where('decision', 'accepted')),
            'rejected' => Tab::make('Ditolak')->query(fn (Builder $query): Builder => $query->where('decision', 'rejected')),
            'waiting_list' => Tab::make('Daftar Tunggu')->query(fn (Builder $query): Builder => $query->where('decision', 'waiting_list')),
        ];
    }
}
