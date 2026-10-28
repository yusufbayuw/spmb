<?php

namespace App\Filament\Admin\Resources\OnlineExamItemResource\Pages;

use App\Filament\Admin\Resources\OnlineExamItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnlineExamItems extends ListRecords
{
    protected static string $resource = OnlineExamItemResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
