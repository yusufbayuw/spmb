<?php

namespace App\Filament\Admin\Resources\UnitResource\Pages;

use App\Filament\Admin\Resources\UnitResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\EditRecord;

class EditUnit extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = UnitResource::class;

    /**
     * A manipulated Livewire payload cannot change system-owned unit fields.
     * Only public-profile attributes may be saved by the own-unit admin.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        if ($user?->isAdminUnit()) {
            abort_unless(
                $user->is_active
                    && $user->hasOperationalUnitAccess()
                    && (int) $this->record->getKey() === (int) $user->unit_id,
                403,
            );

            return \Illuminate\Support\Arr::only($data, [
                'name',
                'description',
                'public_contact_name',
                'public_email',
                'public_phone',
                'public_whatsapp',
                'public_service_hours',
                'public_website_url',
                'public_address',
            ]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
