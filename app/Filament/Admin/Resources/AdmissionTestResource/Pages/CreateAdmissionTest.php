<?php

namespace App\Filament\Admin\Resources\AdmissionTestResource\Pages;

use App\Filament\Admin\Resources\AdmissionTestResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmissionTest extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AdmissionTestResource::class;
}
