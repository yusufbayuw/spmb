<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Overview extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Ringkasan Eksekutif';

    protected static ?string $title = 'Ringkasan Eksekutif';

    protected static ?int $navigationSort = 1;

    public static function analyticsSection(): string
    {
        return 'overview';
    }
}
