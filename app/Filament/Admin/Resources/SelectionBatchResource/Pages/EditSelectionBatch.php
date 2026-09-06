<?php

namespace App\Filament\Admin\Resources\SelectionBatchResource\Pages;

use App\Filament\Admin\Resources\SelectionBatchResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSelectionBatch extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SelectionBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
