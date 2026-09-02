<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserDashboardController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
    
    // Registration Routes
    Route::get('/registration/create', [RegistrationController::class, 'create'])->name('registration.create');
    Route::post('/registration/store', [RegistrationController::class, 'store'])->name('registration.store');
    Route::get('/registration/{id}/documents', [RegistrationController::class, 'uploadDocumentsForm'])->name('registration.documents');
    Route::post('/registration/{id}/documents', [RegistrationController::class, 'uploadDocuments'])->name('registration.documents.upload');
    
    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';