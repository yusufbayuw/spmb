<?php

namespace App\Filament\Admin\Resources\OnlineExamCategoryResource\Pages;

use App\Filament\Admin\Resources\OnlineExamCategoryResource;
use App\Services\OnlineExamAccess;
use Filament\Resources\Pages\EditRecord;

class EditOnlineExamCategory extends EditRecord
{
    protected static string $resource = OnlineExamCategoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(
            app(OnlineExamAccess::class)->canManageUnit(auth()->user(), (int) $this->record->unit_id),
            403,
        );

        $data['unit_id'] = $this->record->unit_id;

        return $data;
    }
}
