<?php

namespace App\Filament\Applicant\Resources\RegistrationResource\Pages;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Services\ApplicantFileStorage;
use App\Services\ConfiguredRegistrationForm;
use App\Services\RegistrationRegionService;
use App\Services\RegistrationSupplementalDataService;
use App\Services\SpmbNotificationService;
use Filament\Resources\Pages\EditRecord;

class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    private array $validatedAcademicScores = [];

    private array $validatedAchievements = [];

    private array $replacedCustomFilePaths = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $supplemental = app(RegistrationSupplementalDataService::class);
        $this->record->loadMissing(['academicScores', 'achievements', 'configuration']);

        $answers = is_array($data['custom_answers'] ?? null) ? $data['custom_answers'] : [];
        foreach ($this->record->configuration?->fields ?? [] as $field) {
            if (($field['type'] ?? null) === 'file') {
                unset($answers[$field['key']]);
            }
        }
        $data['custom_answers'] = $answers;

        return array_merge($data, [
            'academic_scores' => $supplemental->academicScoresFormState($this->record),
            'achievements' => $supplemental->achievementsFormState($this->record),
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

        $regionService = app(RegistrationRegionService::class);

        if ($configuredForm->hasActiveRegionFields($this->record->configuration)
            && $regionService->shouldNormalize($data, $this->record)) {
            $data = $regionService->normalize($data);
        }

        $answers = is_array($data['custom_answers'] ?? null) ? $data['custom_answers'] : [];
        $currentAnswers = is_array($this->record->custom_answers) ? $this->record->custom_answers : [];

        foreach ($this->record->configuration?->fields ?? [] as $field) {
            if (($field['type'] ?? null) !== 'file') {
                continue;
            }

            $key = (string) $field['key'];
            $newPath = $answers[$key] ?? null;
            $oldPath = $currentAnswers[$key] ?? null;

            if (blank($newPath) && filled($oldPath)) {
                $answers[$key] = $oldPath;
            } elseif (is_string($newPath) && is_string($oldPath) && $newPath !== $oldPath) {
                $this->replacedCustomFilePaths[] = $oldPath;
            }
        }

        $data['custom_answers'] = $configuredForm->validateAnswers($this->record->configuration, $answers);

        $pathway = $this->record->pathway()->firstOrFail();

        $supplemental = app(RegistrationSupplementalDataService::class);
        $this->validatedAcademicScores = $supplemental->validateAcademicScores(
            $this->record->configuration,
            $pathway->uuid,
            $data['academic_scores'] ?? [],
        );
        $this->validatedAchievements = $supplemental->validateAchievements(
            $this->record->configuration,
            $pathway->uuid,
            $data['achievements'] ?? [],
        );
        unset($data['academic_scores'], $data['achievements']);

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
        app(RegistrationSupplementalDataService::class)->sync(
            $this->record,
            $this->validatedAcademicScores,
            $this->validatedAchievements,
        );

        foreach (array_unique($this->replacedCustomFilePaths) as $path) {
            app(ApplicantFileStorage::class)->delete($path);
        }
        $this->replacedCustomFilePaths = [];

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
