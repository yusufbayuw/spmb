<?php

use App\Filament\Auth\Pages\Login as UnifiedLogin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Services\PortalDestinationService;
use Filament\Http\Middleware\SetUpPanel;
use Illuminate\Support\Facades\Route;

$authenticatedPortal = static function (): ?string {
    $user = auth()->user();

    return $user
        ? app(PortalDestinationService::class)->pathFor($user)
        : null;
};

Route::get('login', UnifiedLogin::class)
    ->middleware(SetUpPanel::class.':pendaftar')
    ->name('login');

Route::get('register', function () use ($authenticatedPortal) {
    if ($destination = $authenticatedPortal()) {
        return redirect($destination);
    }

    return redirect('/pendaftar/register');
})->name('register');

Route::get('forgot-password', function () use ($authenticatedPortal) {
    if ($destination = $authenticatedPortal()) {
        return redirect($destination);
    }

    return redirect('/pendaftar/password-reset/request');
})->name('password.request');

// Keep old reset links valid for emails that may already have been sent before
// the applicant portal migration. New reset requests are handled by Filament.
Route::middleware('guest')->group(function () {
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
