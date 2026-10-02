<?php

namespace App\Filament\Admin\Resources\TrainingModuleAssessmentResource\Pages;

use App\Filament\Admin\Resources\TrainingModuleAssessmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrainingModuleAssessments extends ListRecords
{
    protected static string $resource = TrainingModuleAssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
