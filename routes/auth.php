<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use Illuminate\Support\Facades\Route;

$authenticatedPortal = static function (): string {
    return auth()->user()?->hasAnyRole(['super_admin', 'tu']) ? '/admin' : '/pendaftar';
};

// Compatibility aliases for the old Breeze URLs. The canonical applicant auth
// UI lives under /pendaftar and is provided by Filament.
Route::get('login', function () use ($authenticatedPortal) {
    return redirect(auth()->check() ? $authenticatedPortal() : '/pendaftar/login');
})->name('login');

Route::get('register', function () use ($authenticatedPortal) {
    return redirect(auth()->check() ? $authenticatedPortal() : '/pendaftar/register');
})->name('register');

Route::get('forgot-password', function () use ($authenticatedPortal) {
    return redirect(auth()->check() ? $authenticatedPortal() : '/pendaftar/password-reset/request');
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
