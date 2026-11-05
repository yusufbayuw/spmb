<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Operations extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Operasional & Kualitas';

    protected static ?string $title = 'Operasional dan Kualitas Data';

    protected static ?int $navigationSort = 10;

    public static function analyticsSection(): string
    {
        return 'operations';
    }
}
