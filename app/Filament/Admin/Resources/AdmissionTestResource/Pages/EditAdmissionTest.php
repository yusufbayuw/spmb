<?php

namespace App\Filament\Admin\Resources\AdmissionTestResource\Pages;

use App\Filament\Admin\Resources\AdmissionTestResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\EditRecord;

class EditAdmissionTest extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AdmissionTestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
