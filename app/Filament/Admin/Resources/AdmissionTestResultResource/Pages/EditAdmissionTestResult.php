<?php

namespace App\Filament\Admin\Resources\AdmissionTestResultResource\Pages;

use App\Filament\Admin\Resources\AdmissionTestResultResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\EditRecord;

class EditAdmissionTestResult extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = AdmissionTestResultResource::class;
}
