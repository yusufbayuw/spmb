<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Models\Registration;
use App\Services\RegistrationWorkflowService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Registration $record */
        $validationStatus = $data['data_validation_status'] ?? $record->data_validation_status;
        $validationNotes = $data['data_validation_notes'] ?? $record->data_validation_notes;

        unset(
            $data['data_validation_status'],
            $data['data_validation_notes'],
            $data['data_validated_at'],
        );

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

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Pendaftaran berhasil diperbarui';
    }
}
