<?php

use App\Http\Controllers\AdmissionOfferController;
use App\Http\Controllers\AdmissionQrCodeController;
use App\Http\Controllers\Auth\ApplicantEmailVerificationController;
use App\Http\Controllers\BrandMediaController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\HomeController;
use App\Filament\Legal\Pages\Privacy as PrivacyPolicyPage;
use App\Filament\Legal\Pages\Terms as TermsPolicyPage;
use App\Http\Controllers\OperationalReportController;
use App\Http\Controllers\PrivateApplicantFileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PwaManifestController;
use App\Http\Controllers\PublicRegistrationOpeningController;
use App\Http\Controllers\PublicUnitAdmissionsController;
use App\Http\Controllers\RegistrationPrintController;
use App\Http\Controllers\StartRegistrationController;
use App\Http\Middleware\EnsureApplicantEmailIsVerified;
use App\Http\Middleware\ValidateApplicantEmailVerificationSignature;
use App\Services\PortalDestinationService;
use Filament\Http\Middleware\SetUpPanel;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/manifest.webmanifest', PwaManifestController::class)->name('pwa.manifest');
Route::get('/media/branding/{path}', BrandMediaController::class)
    ->where('path', '.*')
    ->name('branding.media');
Route::get('/legal/terms', TermsPolicyPage::class)
    ->middleware(SetUpPanel::class.':pendaftar')
    ->name('legal.terms');
Route::get('/legal/privacy', PrivacyPolicyPage::class)
    ->middleware(SetUpPanel::class.':pendaftar')
    ->name('legal.privacy');
Route::get('/penerimaan/unit/{unit:code}/qr.svg', [AdmissionQrCodeController::class, 'unit'])->name('admissions.unit.qr');
Route::get('/penerimaan/unit/{unit:code}', PublicUnitAdmissionsController::class)->name('admissions.unit');
Route::get('/penerimaan/{registrationOpening}/qr.svg', [AdmissionQrCodeController::class, 'opening'])->whereUuid('registrationOpening')->name('admissions.qr');
Route::get('/penerimaan/{registrationOpening}', PublicRegistrationOpeningController::class)->whereUuid('registrationOpening')->name('admissions.show');
Route::get('/penerimaan/{registrationOpening}/daftar', StartRegistrationController::class)->whereUuid('registrationOpening')->name('admissions.apply');
Route::get('/verifikasi/kartu/{registration}', [RegistrationPrintController::class, 'verifyCard'])
    ->whereUuid('registration')
    ->name('registration.card.verify');
Route::get('/verifikasi/kartu-tes/{registration}', [RegistrationPrintController::class, 'verifyTestCard'])
    ->whereUuid('registration')
    ->name('registration.test-card.verify');
Route::get('/verifikasi/sertifikat/{certificate}', [CertificateVerificationController::class, 'show'])
    ->whereUuid('certificate')
    ->name('certificates.verify');
Route::get('/verifikasi/sertifikat/{certificate}/qr.svg', [CertificateVerificationController::class, 'qr'])
    ->whereUuid('certificate')
    ->name('certificates.qr');
Route::get('/verifikasi/sertifikat/{certificate}/artifact.pdf', [CertificateVerificationController::class, 'pdf'])
    ->whereUuid('certificate')
    ->name('certificates.pdf');

Route::get('/pendaftar/email-verification/uuid-verify/{user}/{hash}', ApplicantEmailVerificationController::class)
    ->middleware(['auth', ValidateApplicantEmailVerificationSignature::class, 'throttle:6,1'])
    ->whereUuid('user')
    ->name('applicant.email-verification.verify');

Route::get('/pendaftar/email-verification/verify/{id}/{hash}', fn () => abort(404))
    ->whereNumber('id')
    ->name('applicant.email-verification.legacy');

Route::middleware(['auth', 'throttle:30,1'])
    ->prefix('push')
    ->name('push.')
    ->group(function (): void {
        Route::get('/vapid-public-key', [PushSubscriptionController::class, 'publicKey'])
            ->name('vapid-public-key');
        Route::post('/subscriptions', [PushSubscriptionController::class, 'store'])
            ->name('subscriptions.store');
        Route::delete('/subscriptions', [PushSubscriptionController::class, 'destroy'])
            ->name('subscriptions.destroy');
    });

Route::middleware(['auth', EnsureApplicantEmailIsVerified::class])->group(function () {
    Route::get('/files/applicant/documents/{document}', [PrivateApplicantFileController::class, 'document'])
        ->whereUuid('document')
        ->name('files.applicant.documents.show');
    Route::get('/files/applicant/registrations/{registration}/custom-fields/{key}', [PrivateApplicantFileController::class, 'registrationCustomField'])
        ->whereUuid('registration')
        ->name('files.applicant.registration-custom-field');

    Route::get('/files/applicant/achievements/{achievement}/certificate', [PrivateApplicantFileController::class, 'achievementCertificate'])
        ->whereUuid('achievement')
        ->name('files.applicant.achievements.certificate');

    Route::get('/files/applicant/payments/{payment}/proof', [PrivateApplicantFileController::class, 'paymentProof'])
        ->whereUuid('payment')
        ->name('files.applicant.payments.proof');
    Route::get('/files/applicant/re-registration/{reRegistrationItem}', [PrivateApplicantFileController::class, 'reRegistrationItem'])
        ->whereUuid('reRegistrationItem')
        ->name('files.applicant.re-registration.show');

    Route::get('/admin/reports/operational.xlsx', OperationalReportController::class)
        ->name('reports.operational.xlsx');

    Route::get('/dashboard', function () {
        $destination = app(PortalDestinationService::class)->pathFor(auth()->user());

        abort_unless($destination, 403);

        return redirect($destination);
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
    Route::get('/registration/configurations/{configuration}/field-templates/{key}', [RegistrationPrintController::class, 'formFieldTemplate'])
        ->whereUuid('configuration')
        ->name('registration.form-field-template');

    Route::get('/registration/{registration}/card', [RegistrationPrintController::class, 'applicantCard'])
        ->whereUuid('registration')
        ->name('registration.card');

    Route::post('/registration/admission-offers/{offer}/accept', [AdmissionOfferController::class, 'accept'])
        ->whereUuid('offer')
        ->name('admission-offers.accept');
    Route::post('/registration/admission-offers/{offer}/decline', [AdmissionOfferController::class, 'decline'])
        ->whereUuid('offer')
        ->name('admission-offers.decline');

    Route::get('/profile', function () {
        $destination = app(PortalDestinationService::class)->profilePathFor(auth()->user());

        abort_unless($destination, 403);

        return redirect($destination);
    })->name('profile.edit');
});

require __DIR__.'/auth.php';
