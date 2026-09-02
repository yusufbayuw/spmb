<?php

namespace App\Providers\Filament;

use App\Filament\Applicant\Pages\Auth\Register;
use App\Filament\Applicant\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ApplicantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pendaftar')
            ->path('pendaftar')
            ->login()
            ->registration(Register::class)
            ->passwordReset()
            ->profile(isSimple: false)
            ->brandName('SPMB Taruna Bakti')
            ->colors(['primary' => Color::Blue])
            ->discoverResources(
                in: app_path('Filament/Applicant/Resources'),
                for: 'App\\Filament\\Applicant\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Applicant/Pages'),
                for: 'App\\Filament\\Applicant\\Pages',
            )
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('components.file-preview-modal'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
