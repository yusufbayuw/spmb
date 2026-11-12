<?php

namespace App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;

use App\Filament\Admin\Resources\RegistrationOpeningResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\EditRecord;

class EditRegistrationOpening extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = RegistrationOpeningResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()?->isTU()) {
            $data['unit_id'] = auth()->user()->unit_id;
        }

        // TU can operate openings but cannot change applicant-facing statistics.
        if (! (auth()->user()?->isAdmin() || auth()->user()?->isAdminUnit())) {
            unset($data['show_total_applicants'], $data['show_verified_applicants']);
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
