<?php

namespace App\Filament\Admin\Resources\RegistrationPathwayResource\Pages;

use App\Filament\Admin\Resources\RegistrationPathwayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegistrationPathways extends ListRecords
{
    protected static string $resource = RegistrationPathwayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
