<?php

namespace App\Http\Controllers;

use App\Models\PaymentReceipt;
use App\Models\Registration;
use App\Models\TestBooking;
use App\Services\ApplicantFileStorage;
use App\Services\RegistrationCardService;
use App\Services\TestCardEligibilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RegistrationPrintController extends Controller
{
    private function authorizeRegistration(Request $request, Registration $registration): void
    {
        $user = $request->user();
        abort_unless($user?->is_active && ($user->id === $registration->user_id || $user->isAdmin() || ($user->isTU() && (int) $user->unit_id === (int) $registration->unit_id)), 404);
    }

    public function applicantCard(
        Request $request,
        Registration $registration,
        RegistrationCardService $cards,
    ): View {
        $this->authorizeRegistration($request, $registration);
        abort_unless($registration->isOperational() && $registration->applicant_card_number, 404);

        $card = $cards->cardData($registration);

        return view('registration.card', compact('registration', 'card'));
    }

    public function verifyCard(Registration $registration, RegistrationCardService $cards): View
    {
        abort_unless(filled($registration->applicant_card_number), 404);

        $registration->loadMissing(['unit', 'opening.studyProgram', 'pathway']);
        $hasPhoto = $cards->hasIdentityPhoto($registration);
        $isValid = $registration->isOperational() && $hasPhoto;

        return view('registration.card-verification', compact('registration', 'hasPhoto', 'isValid'));
    }

    public function testCard(Request $request, Registration $registration, TestCardEligibilityService $eligibility): View
    {
        $this->authorizeRegistration($request, $registration);
        abort_unless($registration->isOperational() && $eligibility->canPrint($registration), 404);
        $bookings = TestBooking::with(['session', 'admissionTest'])->where('registration_id', $registration->id)->whereNotNull('test_session_id')->get();
        abort_if($bookings->isEmpty(), 404);

        return view('registration.test-card', compact('registration', 'bookings'));
    }

    public function receipt(Request $request, Registration $registration, PaymentReceipt $receipt): View
    {
        $this->authorizeRegistration($request, $registration);
        abort_unless($receipt->payment->registration_id === $registration->id && $receipt->payment->status === 'verified', 404);

        return view('registration.receipt', compact('registration', 'receipt'));
    }

    public function template(Request $request, Registration $registration, string $key, ApplicantFileStorage $storage): BinaryFileResponse
    {
        $this->authorizeRegistration($request, $registration);
        $requirement = collect($registration->documentRequirements())->firstWhere('key', $key);
        $path = $requirement['template_path'] ?? null;
        abort_unless($path && $storage->privateDisk()->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $downloadName = Str::slug((string) ($requirement['label'] ?? $key))
            .($extension !== '' ? '.'.$extension : '');

        return response()->download(
            $storage->privateDisk()->path($path),
            $downloadName,
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
