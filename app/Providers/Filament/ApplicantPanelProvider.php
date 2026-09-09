<?php

namespace App\Providers\Filament;

use App\Filament\Applicant\Pages\Auth\EmailVerificationPrompt;
use App\Filament\Applicant\Pages\Auth\ResetPassword;
use App\Filament\Applicant\Pages\Auth\RequestPasswordReset;
use App\Filament\Applicant\Pages\Auth\Login;
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
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use App\Filament\Support\LocalLoginBackgrounds;

class ApplicantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pendaftar')
            ->path('pendaftar')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->emailVerification(EmailVerificationPrompt::class)
            ->profile(isSimple: false)
            ->brandName('SPMB Taruna Bakti')
            ->colors(['primary' => Color::Blue])
            ->databaseNotifications()
            ->databaseNotificationsPolling(config('spmb.notifications.polling', '15s'))
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
            ->plugins([
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(LocalLoginBackgrounds::make('images/login-pendaftar')),
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
