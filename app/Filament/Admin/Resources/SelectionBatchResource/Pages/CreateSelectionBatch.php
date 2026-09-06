<?php

namespace App\Filament\Admin\Resources\SelectionBatchResource\Pages;

use App\Filament\Admin\Resources\SelectionBatchResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateSelectionBatch extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SelectionBatchResource::class;
}
