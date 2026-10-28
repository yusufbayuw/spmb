<?php

namespace App\Filament\Admin\Resources\OnlineExamCategoryResource\Pages;

use App\Filament\Admin\Resources\OnlineExamCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnlineExamCategories extends ListRecords
{
    protected static string $resource = OnlineExamCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
