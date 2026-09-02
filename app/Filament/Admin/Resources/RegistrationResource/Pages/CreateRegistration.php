<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Models\RegistrationOpening;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['registration_opening_id'])) {
            $opening = RegistrationOpening::query()->find($data['registration_opening_id']);

            if ($opening) {
                $data['unit_id'] = $opening->unit_id;
            }
        }

        return $data;
    }
}
