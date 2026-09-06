<?php

namespace App\Filament\Admin\Resources\RegistrationPathwayResource\Pages;

use App\Filament\Admin\Resources\RegistrationPathwayResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\EditRecord;

class EditRegistrationPathway extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = RegistrationPathwayResource::class;
}
