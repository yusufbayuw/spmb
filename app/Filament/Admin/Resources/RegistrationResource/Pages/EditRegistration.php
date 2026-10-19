<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Filament\RedirectsToResourceIndex;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Services\ConfiguredRegistrationForm;
use App\Services\RegistrationRegionService;
use App\Services\RegistrationWorkflowService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRegistration extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewApplicantCard')
                ->label('Lihat Kartu')
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->visible(fn (): bool => RegistrationResource::canViewApplicantCard($this->record))
                ->url(fn (): string => route('registration.card', $this->record))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Registration $record */
        $validationStatus = $data['data_validation_status'] ?? $record->data_validation_status;
        $validationNotes = $data['data_validation_notes'] ?? $record->data_validation_notes;

        if (! empty($data['registration_opening_id'])) {
            $opening = RegistrationOpening::query()->find($data['registration_opening_id']);

            if ($opening) {
                $data['unit_id'] = $opening->unit_id;
            }
        }

        unset(
            $data['data_validation_status'],
            $data['data_validation_notes'],
            $data['data_validated_at'],
        );

        $regionService = app(RegistrationRegionService::class);

        if ($regionService->shouldNormalize($data, $record)) {
            $data = $regionService->normalize($data);
        }

        // The staff form only edits known non-file fields. Preserve custom
        // answers for private uploads, removed questions and other legacy
        // keys instead of replacing the entire JSON document.
        if (array_key_exists('custom_answers', $data)) {
            $data['custom_answers'] = $this->mergePinnedCustomAnswers(
                $record,
                is_array($data['custom_answers']) ? $data['custom_answers'] : [],
            );
        }

        $record->update($data);

        $canValidate = auth()->user()?->can('validate_data_registration') ?? false;

        if ($canValidate && $record->current_stage === 'data_validation') {
            if ($validationStatus === 'valid') {
                app(RegistrationWorkflowService::class)->validateData(
                    $record,
                    auth()->user(),
                    true,
                    $validationNotes,
                );

                $record->refresh();

                if ($record->current_stage === 'payment') {
                    Notification::make()
                        ->title('Data valid & VA otomatis dikirim')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Data valid, tetapi pool VA unit kosong')
                        ->body('Upload pool VA agar sistem dapat melakukan assignment otomatis.')
                        ->warning()
                        ->send();
                }
            } elseif ($validationStatus === 'revision') {
                app(RegistrationWorkflowService::class)->validateData(
                    $record,
                    auth()->user(),
                    false,
                    $validationNotes,
                );

                Notification::make()
                    ->title('Data dikembalikan untuk revisi')
                    ->warning()
                    ->send();
            } else {
                $record->update([
                    'data_validation_status' => 'pending',
                    'data_validation_notes' => $validationNotes,
                ]);
            }
        }

        return $record->fresh();
    }

    /**
     * Validate only answers exposed by the registration's own versioned
     * configuration. Never rewrite unrelated historical keys or file paths.
     *
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private function mergePinnedCustomAnswers(Registration $record, array $incoming): array
    {
        $original = is_array($record->custom_answers) ? $record->custom_answers : [];
        $configuration = $record->configuration;

        if (! $configuration) {
            return $original;
        }

        $formService = app(ConfiguredRegistrationForm::class);
        $definitions = $formService->staffEditableCustomDefinitions($configuration);
        $allowed = array_fill_keys(array_column($definitions, 'key'), true);
        $submitted = array_intersect_key($incoming, $allowed);

        if ($submitted === []) {
            return $original;
        }

        // Only validate the questions submitted from the staff editor. Old
        // answers to removed questions or legacy files may not conform to
        // today's validation rules and must not block unrelated corrections.
        $selectedDefinitions = array_values(array_filter(
            $definitions,
            static fn (array $field): bool => array_key_exists($field['key'], $submitted),
        ));
        $validationConfiguration = clone $configuration;
        $validationConfiguration->fields = $selectedDefinitions;

        $booleanKeys = array_column(array_filter(
            $selectedDefinitions,
            static fn (array $field): bool => $field['type'] === 'boolean',
        ), 'key');

        if (is_array($incoming['_details'] ?? null) && $booleanKeys !== []) {
            $submitted['_details'] = array_intersect_key(
                $incoming['_details'],
                array_fill_keys($booleanKeys, true),
            );
        }

        $validated = $formService->validateAnswers($validationConfiguration, $submitted);

        foreach (array_keys($submitted) as $key) {
            if ($key === '_details') {
                continue;
            }

            $original[$key] = $validated[$key] ?? null;
        }

        foreach ($booleanKeys as $key) {
            if (array_key_exists($key, $validated['_details'] ?? [])) {
                $original['_details'][$key] = $validated['_details'][$key];
            } else {
                unset($original['_details'][$key]);
            }
        }

        return $original;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Pendaftaran berhasil diperbarui';
    }
}
