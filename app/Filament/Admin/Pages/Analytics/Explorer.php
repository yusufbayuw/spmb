<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Explorer extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Eksplorasi Data';

    protected static ?string $title = 'Eksplorasi Data';

    protected static ?int $navigationSort = 11;

    public static function analyticsSection(): string
    {
        return 'explorer';
    }
}
