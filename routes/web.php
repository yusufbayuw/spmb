<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', fn () => redirect('/pendaftar'))->name('dashboard');

    Route::get('/registration/create', [RegistrationController::class, 'create'])->name('registration.create');
    Route::post('/registration', [RegistrationController::class, 'store'])->name('registration.store');
    Route::get('/registration/{registration}', [RegistrationController::class, 'show'])->whereNumber('registration')->name('registration.show');
    Route::get('/registration/{registration}/payment', [RegistrationController::class, 'paymentForm'])->whereNumber('registration')->name('registration.payment');
    Route::post('/registration/{registration}/payment', [RegistrationController::class, 'uploadPayment'])->whereNumber('registration')->name('registration.payment.upload');
    Route::get('/registration/{registration}/documents', [RegistrationController::class, 'documentsForm'])->whereNumber('registration')->name('registration.documents');
    Route::post('/registration/{registration}/documents', [RegistrationController::class, 'uploadDocuments'])->whereNumber('registration')->name('registration.documents.upload');
    Route::get('/registration/{registration}/card', [RegistrationController::class, 'card'])->whereNumber('registration')->name('registration.card');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
