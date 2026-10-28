<?php

namespace App\Filament\Admin\Resources\OnlineExamResource\Pages;

use App\Filament\Admin\Resources\OnlineExamResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnlineExams extends ListRecords
{
    protected static string $resource = OnlineExamResource::class;

    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
