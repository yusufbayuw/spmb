<?php

namespace App\Filament\Admin\Resources\StudyProgramResource\Pages;

use App\Filament\Admin\Resources\StudyProgramResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStudyPrograms extends ListRecords
{
    protected static string $resource = StudyProgramResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'active' => Tab::make('Aktif')->query(fn (Builder $query): Builder => $query->where('is_active', true)),
            'inactive' => Tab::make('Nonaktif')->query(fn (Builder $query): Builder => $query->where('is_active', false)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Tambah Program Studi')];
    }
}
