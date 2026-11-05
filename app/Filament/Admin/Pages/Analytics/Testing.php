<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Testing extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Tes & CBT';

    protected static ?string $title = 'Analitik Tes dan CBT';

    protected static ?int $navigationSort = 7;

    public static function analyticsSection(): string
    {
        return 'testing';
    }
}
