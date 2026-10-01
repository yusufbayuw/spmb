<?php

namespace App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;

use App\Filament\Admin\Resources\RegistrationOpeningResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRegistrationOpening extends ViewRecord
{
    protected static string $resource = RegistrationOpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pause')
                ->label('Pause Pendaftaran')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => RegistrationOpeningResource::canEdit($this->record)
                    && ! $this->record->isPaused()
                    && in_array($this->record->operationalStatus(), ['open', 'scheduled'], true))
                ->action(function (): void {
                    $this->record->pause(auth()->user());
                    $this->record->refresh();

                    Notification::make()
                        ->title('Pendaftaran dipause')
                        ->body('Pembukaan langsung disembunyikan dari seluruh area publik.')
                        ->warning()
                        ->send();
                }),
            Actions\Action::make('resume')
                ->label('Start Pendaftaran')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => RegistrationOpeningResource::canEdit($this->record) && $this->record->isPaused())
                ->action(function (): void {
                    $this->record->resume();
                    $this->record->refresh();

                    Notification::make()
                        ->title('Pendaftaran di-start kembali')
                        ->body($this->record->isOpen()
                            ? 'Pembukaan kembali tampil dan menerima pendaftaran baru.'
                            : 'Pause dilepas. Pembukaan kembali mengikuti jadwal yang tersimpan.')
                        ->success()
                        ->send();
                }),
            Actions\EditAction::make()->label('Edit Gelombang'),
        ];
    }
}
