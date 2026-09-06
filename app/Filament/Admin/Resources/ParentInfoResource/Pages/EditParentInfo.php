<?php

namespace App\Filament\Admin\Resources\ParentInfoResource\Pages;

use App\Filament\Admin\Resources\ParentInfoResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParentInfo extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ParentInfoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
