<?php

namespace App\Filament\Admin\Resources\AdmissionTestResultResource\Pages;

use App\Filament\Admin\Resources\AdmissionTestResultResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAdmissionTestResults extends ListRecords
{
    protected static string $resource = AdmissionTestResultResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'scheduled' => Tab::make('Terjadwal')->query(fn (Builder $query): Builder => $query->where('status', 'scheduled')),
            'completed' => Tab::make('Selesai')->query(fn (Builder $query): Builder => $query->where('status', 'completed')),
            'absent' => Tab::make('Tidak Hadir')->query(fn (Builder $query): Builder => $query->where('status', 'absent')),
            'exempted' => Tab::make('Dibebaskan')->query(fn (Builder $query): Builder => $query->where('status', 'exempted')),
        ];
    }
}
