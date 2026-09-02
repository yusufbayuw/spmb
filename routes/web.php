<?php

use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect('/pendaftar'))->name('dashboard');

    // Legacy applicant URLs are compatibility redirects only. All applicant data
    // changes now happen inside the Filament applicant panel.
    Route::get('/registration/create', fn () => redirect('/pendaftar/registrations/create'))
        ->name('registration.create');
    Route::get('/registration/{registration}', fn (int $registration) => redirect("/pendaftar/status/{$registration}"))
        ->whereNumber('registration')
        ->name('registration.show');
    Route::get('/registration/{registration}/payment', fn (int $registration) => redirect("/pendaftar/pembayaran/{$registration}"))
        ->whereNumber('registration')
        ->name('registration.payment');
    Route::get('/registration/{registration}/documents', fn (int $registration) => redirect("/pendaftar/dokumen/{$registration}"))
        ->whereNumber('registration')
        ->name('registration.documents');
    Route::get('/registration/{registration}/card', [RegistrationController::class, 'card'])
        ->whereNumber('registration')
        ->name('registration.card');

    Route::get('/profile', fn () => redirect('/pendaftar/profile'))->name('profile.edit');
});

require __DIR__.'/auth.php';
