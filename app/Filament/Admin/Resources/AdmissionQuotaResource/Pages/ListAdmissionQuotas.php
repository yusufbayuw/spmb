<?php

namespace App\Filament\Admin\Resources\AdmissionQuotaResource\Pages;

use App\Filament\Admin\Resources\AdmissionQuotaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdmissionQuotas extends ListRecords
{
    protected static string $resource = AdmissionQuotaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
