<?php

namespace App\Filament\Admin\Resources\OnlineExamResource\Pages;

use App\Filament\Admin\Resources\OnlineExamResource;
use App\Services\OnlineExamAccess;
use Filament\Resources\Pages\EditRecord;

class EditOnlineExam extends EditRecord
{
    protected static string $resource = OnlineExamResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(app(OnlineExamAccess::class)->canManage(auth()->user(), $this->record), 403);
        $data['unit_id'] = $this->record->unit_id;

        return $data;
    }
}
