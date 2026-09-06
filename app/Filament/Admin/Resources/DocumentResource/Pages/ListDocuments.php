<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'pending' => Tab::make('Menunggu Verifikasi')->query(fn (Builder $query): Builder => $query->where('is_verified', false)->whereNull('rejection_reason')),
            'rejected' => Tab::make('Ditolak')->query(fn (Builder $query): Builder => $query->whereNotNull('rejection_reason')),
            'verified' => Tab::make('Terverifikasi')->query(fn (Builder $query): Builder => $query->where('is_verified', true)),
        ];
    }
}
