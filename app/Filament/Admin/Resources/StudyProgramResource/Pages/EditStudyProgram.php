<?php

namespace App\Filament\Admin\Resources\StudyProgramResource\Pages;

use App\Filament\Admin\Resources\StudyProgramResource;
use Filament\Resources\Pages\EditRecord;

class EditStudyProgram extends EditRecord
{
    protected static string $resource = StudyProgramResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()?->isTU()) {
            $data['unit_id'] = auth()->user()->unit_id;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
