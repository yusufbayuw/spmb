<?php

namespace App\Filament\Applicant\Pages;

use Filament\Pages\Page;

class DeviceNotifications extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationLabel = 'Notifikasi Perangkat';
    protected static ?string $title = 'Notifikasi Perangkat';
    protected static ?string $navigationGroup = 'Akun';
    protected static ?int $navigationSort = 50;
    protected static string $view = 'filament.pages.device-notifications';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active && auth()->user()?->isUser();
    }
}
