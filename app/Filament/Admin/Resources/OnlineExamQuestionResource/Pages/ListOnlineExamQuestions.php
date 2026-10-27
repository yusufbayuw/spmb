<?php

namespace App\Filament\Admin\Resources\OnlineExamQuestionResource\Pages;

use App\Filament\Admin\Resources\OnlineExamQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnlineExamQuestions extends ListRecords
{
    protected static string $resource = OnlineExamQuestionResource::class;

    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
