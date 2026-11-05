<?php

namespace App\Filament\Admin\Pages\Analytics;

use App\Filament\Admin\Support\AnalyticsPage;

class Profile extends AnalyticsPage
{
    protected static ?string $navigationLabel = 'Profil & Demografi';

    protected static ?string $title = 'Profil Pendaftar';

    protected static ?int $navigationSort = 3;

    public static function analyticsSection(): string
    {
        return 'profile';
    }
}
