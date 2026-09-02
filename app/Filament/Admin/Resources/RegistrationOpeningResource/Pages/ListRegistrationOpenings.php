<?php

namespace App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;

use App\Filament\Admin\Resources\RegistrationOpeningResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegistrationOpenings extends ListRecords
{
    protected static string $resource = RegistrationOpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Buat Pembukaan'),
        ];
    }
}
