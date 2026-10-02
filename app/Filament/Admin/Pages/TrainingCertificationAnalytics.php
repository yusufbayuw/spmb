<?php

namespace App\Filament\Admin\Pages;

use App\Services\TrainingAnalyticsService;
use Filament\Pages\Page;

class TrainingCertificationAnalytics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Analytics Kompetensi';
    protected static ?string $title = 'Analytics Training & Sertifikasi';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 25;
    protected static string $view = 'filament.admin.pages.training-certification-analytics';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->is_active
            && ($user->isAdmin() || ($user->isAdminUnit() && $user->hasOperationalUnitAccess()));
    }

    public function data(): array
    {
        return app(TrainingAnalyticsService::class)->dashboard(auth()->user());
    }
}
