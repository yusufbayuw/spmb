<?php

namespace App\Filament\Admin\Resources\AdmissionTestResultResource\Pages;

use App\Filament\Admin\Resources\AdmissionTestResultResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmissionTestResult extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AdmissionTestResultResource::class;
}
