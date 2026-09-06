<?php

namespace App\Filament\Admin\Resources\ReRegistrationItemResource\Pages;

use App\Filament\Admin\Resources\ReRegistrationItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReRegistrationItem extends EditRecord
{
    protected static string $resource = ReRegistrationItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
