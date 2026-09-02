<?php

namespace App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;

use App\Filament\Admin\Resources\RegistrationOpeningResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistrationOpening extends CreateRecord
{
    protected static string $resource = RegistrationOpeningResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (auth()->user()?->isTU()) {
            $data['unit_id'] = auth()->user()->unit_id;
        }

        if (($data['status'] ?? 'draft') === 'open') {
            $data['opened_at'] = now();
        }

        return $data;
    }
}
