<?php

namespace App\Filament\Admin\Resources\FaqResource\Pages;

use App\Filament\Admin\Resources\FaqResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Resources\Pages\EditRecord;

class EditFaq extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
