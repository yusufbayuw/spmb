<?php

namespace App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;

use App\Filament\Admin\Resources\RegistrationOpeningResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegistrationOpening extends EditRecord
{
    protected static string $resource = RegistrationOpeningResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()?->isTU()) {
            $data['unit_id'] = auth()->user()->unit_id;
        }

        $status = $data['status'] ?? $this->record->status;

        if ($status === 'open' && $this->record->status !== 'open') {
            $data['opened_at'] = now();
            $data['closed_at'] = null;
            $data['archived_at'] = null;
        } elseif ($status === 'closed' && $this->record->status !== 'closed') {
            $data['closed_at'] = now();
        } elseif ($status === 'archived' && $this->record->status !== 'archived') {
            $data['archived_at'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
