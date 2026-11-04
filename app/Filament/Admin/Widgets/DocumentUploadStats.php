<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\DocumentResource;
use App\Services\DocumentUploadOverview;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DocumentUploadStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        return [
            Stat::make('Pendaftar Sudah Upload', number_format(app(DocumentUploadOverview::class)->count(), 0, ',', '.'))
                ->description('Klik untuk rincian kategori berkas per pendaftar')
                ->descriptionIcon('heroicon-m-arrow-top-right-on-square')
                ->icon('heroicon-o-document-arrow-up')
                ->url(DocumentResource::getUrl('index', ['uploaders' => 1])),
        ];
    }
}
