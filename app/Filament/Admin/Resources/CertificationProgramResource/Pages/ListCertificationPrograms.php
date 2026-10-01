<?php

namespace App\Filament\Admin\Resources\CertificationProgramResource\Pages;

use App\Filament\Admin\Resources\CertificationProgramResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCertificationPrograms extends ListRecords
{
    protected static string $resource = CertificationProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
