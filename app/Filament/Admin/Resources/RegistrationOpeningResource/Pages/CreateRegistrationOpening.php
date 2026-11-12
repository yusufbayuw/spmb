<?php

namespace App\Filament\Admin\Resources\RegistrationOpeningResource\Pages;

use App\Filament\Admin\Resources\RegistrationOpeningResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistrationOpening extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = RegistrationOpeningResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (auth()->user()?->isTU()) {
            $data['unit_id'] = auth()->user()->unit_id;
        }

        // TU can operate openings but cannot change applicant-facing statistics.
        if (! (auth()->user()?->isAdmin() || auth()->user()?->isAdminUnit())) {
            unset($data['show_total_applicants'], $data['show_verified_applicants']);
        }

        if (($data['status'] ?? 'draft') === 'open') {
            $data['opened_at'] = now();
        }

        return $data;
    }
}
