<?php

namespace App\Filament\Admin\Resources\TrainingModuleAssessmentResource\Pages;

use App\Filament\Admin\Resources\TrainingModuleAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrainingModuleAssessment extends EditRecord
{
    protected static string $resource = TrainingModuleAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
