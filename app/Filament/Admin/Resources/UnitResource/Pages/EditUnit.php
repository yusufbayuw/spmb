<?php

namespace App\Filament\Admin\Resources\UnitResource\Pages;

use App\Filament\Admin\Resources\UnitResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\EditRecord;

class EditUnit extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = UnitResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->isAdmin()) {
            unset($data['allow_admin_unit_registration_purge']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
