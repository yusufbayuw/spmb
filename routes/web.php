<?php

use App\Http\Controllers\Auth\ApplicantEmailVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OperationalReportController;
use App\Http\Controllers\PrivateApplicantFileController;
use App\Http\Controllers\RegistrationPrintController;
use App\Http\Middleware\EnsureApplicantEmailIsVerified;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/pendaftar/email-verification/uuid-verify/{user}/{hash}', ApplicantEmailVerificationController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->whereUuid('user')
    ->name('applicant.email-verification.verify');

Route::get('/pendaftar/email-verification/verify/{id}/{hash}', fn () => abort(404))
    ->whereNumber('id')
    ->name('applicant.email-verification.legacy');

Route::middleware(['auth', EnsureApplicantEmailIsVerified::class])->group(function () {
    Route::get('/files/applicant/documents/{document}', [PrivateApplicantFileController::class, 'document'])
        ->whereUuid('document')
        ->name('files.applicant.documents.show');

    Route::get('/files/applicant/payments/{payment}/proof', [PrivateApplicantFileController::class, 'paymentProof'])
        ->whereUuid('payment')
        ->name('files.applicant.payments.proof');

    Route::get('/admin/reports/operational.xlsx', OperationalReportController::class)
        ->name('reports.operational.xlsx');

    Route::get('/dashboard', function () {
        return redirect(auth()->user()->hasAnyRole(['super_admin', 'tu']) ? '/admin' : '/pendaftar');
    })->name('dashboard');

    Route::get('/registration/create', fn () => redirect('/pendaftar/pendaftaran'))
        ->name('registration.create');
    Route::get('/registration/{registration}', fn (string $registration) => redirect("/pendaftar/status/{$registration}"))
        ->whereUuid('registration')
        ->name('registration.show');
    Route::get('/registration/{registration}/payment', fn (string $registration) => redirect("/pendaftar/pembayaran/{$registration}"))
        ->whereUuid('registration')
        ->name('registration.payment');
    Route::get('/registration/{registration}/documents', fn (string $registration) => redirect("/pendaftar/dokumen/{$registration}"))
        ->whereUuid('registration')
        ->name('registration.documents');
    Route::get('/registration/{registration}/test-card', [RegistrationPrintController::class, 'testCard'])->whereUuid('registration')->name('registration.test-card');
    Route::get('/registration/{registration}/receipts/{receipt}', [RegistrationPrintController::class, 'receipt'])->whereUuid(['registration', 'receipt'])->name('registration.receipt');
    Route::get('/registration/{registration}/templates/{key}', [RegistrationPrintController::class, 'template'])->whereUuid('registration')->name('registration.template');

    Route::get('/registration/{registration}/card', [RegistrationPrintController::class, 'applicantCard'])
        ->whereUuid('registration')
        ->name('registration.card');

    Route::get('/profile', function () {
        return redirect(auth()->user()->hasAnyRole(['super_admin', 'tu']) ? '/admin' : '/pendaftar/profile');
    })->name('profile.edit');
});

require __DIR__.'/auth.php';
