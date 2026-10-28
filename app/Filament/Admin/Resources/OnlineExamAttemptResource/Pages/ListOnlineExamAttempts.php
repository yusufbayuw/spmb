<?php

namespace App\Filament\Admin\Resources\OnlineExamAttemptResource\Pages;

use App\Filament\Admin\Resources\OnlineExamAttemptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOnlineExamAttempts extends ListRecords
{
    protected static string $resource = OnlineExamAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('monitor')
                ->label('Dashboard Pengawas')
                ->icon('heroicon-o-chart-bar-square')
                ->url(fn (): string => OnlineExamAttemptResource::getUrl('monitor')),
        ];
    }
}
