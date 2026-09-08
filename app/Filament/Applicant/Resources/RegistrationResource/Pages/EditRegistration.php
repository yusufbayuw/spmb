<?php

namespace App\Filament\Applicant\Resources\RegistrationResource\Pages;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Services\ConfiguredRegistrationForm;
use App\Services\RegistrationRegionService;
use App\Services\SpmbNotificationService;
use Filament\Resources\Pages\EditRecord;

class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, [
            'registration_opening_uuid' => $this->record->opening?->uuid,
            'registration_pathway_uuid' => $this->record->pathway?->uuid,
            'unit_uuid' => $this->record->unit?->uuid,
            'unit_configuration_uuid' => $this->record->configuration?->uuid,
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['unit_configuration_id'] = $this->record->unit_configuration_id;
        $configuredForm = app(ConfiguredRegistrationForm::class);

        if ($configuredForm->hasActiveRegionFields($this->record->configuration)) {
            $data = app(RegistrationRegionService::class)->normalize($data);
        }

        $data['custom_answers'] = $configuredForm->validateAnswers($this->record->configuration, $data['custom_answers'] ?? []);
        $data['registrant_relationship'] = ($data['registrant_type'] ?? 'parent') === 'self'
            ? 'self'
            : ($data['registrant_relationship'] ?? null);
        unset($data['registration_opening_uuid'], $data['registration_pathway_uuid'], $data['unit_uuid'], $data['unit_configuration_uuid']);

        if (($this->record->data_validation_status ?? null) === 'revision') {
            $data['data_validation_status'] = 'pending';
            $data['data_validation_notes'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        app(SpmbNotificationService::class)->workflowEvent($this->record, 'registration.revised', 'Revisi pendaftaran dikirim', 'Data pendaftaran diperbarui dan menunggu pemeriksaan.', false, true);
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
