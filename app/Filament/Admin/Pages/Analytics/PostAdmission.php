<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class PostAdmission extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Pasca-Penerimaan';

    protected static ?string $title = 'Analitik Pasca-Penerimaan';

    protected static ?int $navigationSort = 9;

    public static function analyticsSection(): string
    {
        return 'post-admission';
    }
}
