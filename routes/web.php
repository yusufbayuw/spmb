<?php

use App\Http\Controllers\OperationalReportController;
use App\Http\Controllers\PrivateApplicantFileController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/files/applicant/documents/{document}', [PrivateApplicantFileController::class, 'document'])
        ->whereNumber('document')
        ->name('files.applicant.documents.show');

    Route::get('/files/applicant/payments/{payment}/proof', [PrivateApplicantFileController::class, 'paymentProof'])
        ->whereNumber('payment')
        ->name('files.applicant.payments.proof');

    Route::get('/admin/reports/operational.xlsx', OperationalReportController::class)
        ->name('reports.operational.xlsx');

    Route::get('/dashboard', function () {
        return redirect(auth()->user()->hasAnyRole(['super_admin', 'tu']) ? '/admin' : '/pendaftar');
    })->name('dashboard');

    Route::get('/registration/create', fn () => redirect('/pendaftar/pendaftaran'))
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

    Route::get('/profile', function () {
        return redirect(auth()->user()->hasAnyRole(['super_admin', 'tu']) ? '/admin' : '/pendaftar/profile');
    })->name('profile.edit');
});

require __DIR__.'/auth.php';
