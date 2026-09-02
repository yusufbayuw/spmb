<?php

namespace App\Filament\Admin\Resources\ParentInfoResource\Pages;

use App\Filament\Admin\Resources\ParentInfoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParentInfo extends EditRecord
{
    protected static string $resource = ParentInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->visible(fn () => auth()->user()?->isAdmin() ?? false),
        ];
    }
}
