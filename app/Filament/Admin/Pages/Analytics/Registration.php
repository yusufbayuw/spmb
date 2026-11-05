<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Registration extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Pendaftaran & Funnel';

    protected static ?string $title = 'Analitik Pendaftaran';

    protected static ?int $navigationSort = 2;

    public static function analyticsSection(): string
    {
        return 'registration';
    }
}
