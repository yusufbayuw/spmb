<?php

namespace App\Filament\Admin\Resources\RegistrationPathwayResource\Pages;

use App\Filament\Admin\Resources\RegistrationPathwayResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistrationPathway extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = RegistrationPathwayResource::class;
}
