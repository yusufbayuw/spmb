<?php

namespace App\Filament\Admin\Resources\AdmissionQuotaResource\Pages;

use App\Filament\Admin\Resources\AdmissionQuotaResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmissionQuota extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AdmissionQuotaResource::class;
}
