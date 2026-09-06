<?php

namespace App\Filament\Admin\Resources\UnitResource\Pages;

use App\Filament\Admin\Resources\UnitResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateUnit extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = UnitResource::class;
}
