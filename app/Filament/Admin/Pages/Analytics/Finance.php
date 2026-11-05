<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Finance extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Pembayaran & VA';

    protected static ?string $title = 'Pembayaran dan VA';

    protected static ?int $navigationSort = 5;

    public static function analyticsSection(): string
    {
        return 'finance';
    }
}
