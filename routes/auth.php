<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\UnifiedLoginController;
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

Route::get('login', [UnifiedLoginController::class, 'create'])
    ->middleware(SetUpPanel::class.':pendaftar')
    ->name('login');

Route::post('login', [UnifiedLoginController::class, 'store'])
    ->middleware('guest')
    ->name('login.store');

Route::get('login/captcha', [UnifiedLoginController::class, 'captcha'])
    ->middleware(['guest', 'throttle:30,1'])
    ->name('login.captcha');

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
