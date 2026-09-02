<?php

namespace App\Filament\Admin\Resources\ParentInfoResource\Pages;

use App\Filament\Admin\Resources\ParentInfoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParentInfos extends ListRecords
{
    protected static string $resource = ParentInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
