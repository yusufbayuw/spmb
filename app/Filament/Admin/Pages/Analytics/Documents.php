<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Documents extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Berkas Pendaftaran';

    protected static ?string $title = 'Analitik Berkas';

    protected static ?int $navigationSort = 6;

    public static function analyticsSection(): string
    {
        return 'documents';
    }
}
