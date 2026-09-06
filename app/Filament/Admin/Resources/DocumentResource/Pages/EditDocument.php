<?php

namespace App\Filament\Admin\Resources\DocumentResource\Pages;

use App\Filament\Admin\Resources\DocumentResource;
use App\Filament\RedirectsToResourceIndex;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['is_verified'] ?? false) {
            $data['verified_at'] = $this->record->verified_at ?? now();
            $data['verified_by'] = $this->record->verified_by ?? auth()->id();
        } else {
            $data['verified_at'] = null;
            $data['verified_by'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->visible(fn () => auth()->user()?->isAdmin() ?? false),
        ];
    }
}
