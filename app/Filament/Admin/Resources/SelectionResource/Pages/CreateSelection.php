<?php

namespace App\Filament\Admin\Resources\SelectionResource\Pages;

use App\Filament\Admin\Resources\SelectionResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateSelection extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SelectionResource::class;
}
