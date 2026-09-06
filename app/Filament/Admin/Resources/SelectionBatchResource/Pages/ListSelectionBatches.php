<?php

namespace App\Filament\Admin\Resources\SelectionBatchResource\Pages;

use App\Filament\Admin\Resources\SelectionBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSelectionBatches extends ListRecords
{
    protected static string $resource = SelectionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
