<?php

namespace App\Filament\Admin\Resources\SelectionResource\Pages;

use App\Filament\Admin\Resources\SelectionResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\EditRecord;

class EditSelection extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SelectionResource::class;
}
