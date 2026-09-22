<?php

namespace App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;

use App\Filament\Admin\Resources\RegistrationOpeningResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRegistrationOpening extends ViewRecord
{
    protected static string $resource = RegistrationOpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Edit Gelombang'),
        ];
    }
}
