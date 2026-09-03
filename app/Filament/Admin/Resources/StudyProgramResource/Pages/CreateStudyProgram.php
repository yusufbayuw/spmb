<?php

namespace App\Filament\Admin\Resources\StudyProgramResource\Pages;

use App\Filament\Admin\Resources\StudyProgramResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudyProgram extends CreateRecord
{
    protected static string $resource = StudyProgramResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->isTU()) {
            $data['unit_id'] = auth()->user()->unit_id;
        }

        return $data;
    }
}
