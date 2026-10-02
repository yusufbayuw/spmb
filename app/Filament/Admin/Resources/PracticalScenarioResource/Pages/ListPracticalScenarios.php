<?php

namespace App\Filament\Admin\Resources\PracticalScenarioResource\Pages;

use App\Filament\Admin\Resources\PracticalScenarioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPracticalScenarios extends ListRecords
{
    protected static string $resource = PracticalScenarioResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
