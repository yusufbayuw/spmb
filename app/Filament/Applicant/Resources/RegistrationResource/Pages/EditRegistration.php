<?php

namespace App\Filament\Applicant\Resources\RegistrationResource\Pages;

use App\Filament\Applicant\Resources\RegistrationResource;
use Filament\Resources\Pages\EditRecord;

class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['registrant_relationship'] = ($data['registrant_type'] ?? 'parent') === 'self'
            ? 'self'
            : ($data['registrant_relationship'] ?? null);

        if (($this->record->data_validation_status ?? null) === 'revision') {
            $data['data_validation_status'] = 'pending';
            $data['data_validation_notes'] = null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Data pendaftaran berhasil diperbarui';
    }
}
