<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;

class DeviceNotifications extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationLabel = 'Notifikasi Perangkat';
    protected static ?string $title = 'Notifikasi Perangkat';
    protected static ?string $navigationGroup = 'Sistem & Akses';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.device-notifications';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active
            && auth()->user()?->hasAnyRole(['super_admin', 'admin_unit', 'tu']);
    }
}
