<?php

namespace App\Filament\Admin\Resources\ParentInfoResource\Pages;

use App\Filament\Admin\Resources\ParentInfoResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateParentInfo extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ParentInfoResource::class;
}
