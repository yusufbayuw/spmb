<?php

namespace App\Filament\Admin\Resources\OnlineExamQuestionResource\Pages;

use App\Filament\Admin\Resources\OnlineExamQuestionResource;
use App\Services\OnlineExamAccess;
use Filament\Resources\Pages\EditRecord;

class EditOnlineExamQuestion extends EditRecord
{
    protected static string $resource = OnlineExamQuestionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        abort_unless(
            app(OnlineExamAccess::class)->canManageUnit(auth()->user(), (int) $this->record->unit_id),
            403,
        );

        // grading_config is intentionally hidden from model serialization so
        // answer keys never leak to participants. Authorized editors still
        // need the existing key in their Filament form.
        $data['grading_config'] = $this->record->grading_config ?? [];

        return $data;
    }

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
