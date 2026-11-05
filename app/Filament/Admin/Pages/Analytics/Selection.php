<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Selection extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Seleksi & Kuota';

    protected static ?string $title = 'Seleksi dan Daya Tampung';

    protected static ?int $navigationSort = 8;

    public static function analyticsSection(): string
    {
        return 'selection';
    }
}
