<?php

namespace App\Filament\Admin\Resources\AdmissionQuotaResource\Pages;

use App\Filament\Admin\Resources\AdmissionQuotaResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdmissionQuota extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AdmissionQuotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
