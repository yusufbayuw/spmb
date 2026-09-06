<?php

namespace App\Filament\Admin\Resources\AnnouncementResource\Pages;

use App\Filament\Admin\Resources\AnnouncementResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'draft' => Tab::make('Belum Dipublikasikan')->query(fn (Builder $query): Builder => $query->where('status', 'draft')),
            'published' => Tab::make('Sudah Dipublikasikan')->query(fn (Builder $query): Builder => $query->where('status', 'published')),
        ];
    }
}
