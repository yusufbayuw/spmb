<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Schools extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Asal Sekolah & Terusan';

    protected static ?string $title = 'Asal Sekolah dan Terusan';

    protected static ?int $navigationSort = 4;

    public static function analyticsSection(): string
    {
        return 'schools';
    }
}
