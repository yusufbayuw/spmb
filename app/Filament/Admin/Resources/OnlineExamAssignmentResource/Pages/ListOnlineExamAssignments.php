<?php

namespace App\Filament\Admin\Resources\OnlineExamAssignmentResource\Pages;

use App\Filament\Admin\Resources\OnlineExamAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnlineExamAssignments extends ListRecords
{
    protected static string $resource = OnlineExamAssignmentResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
