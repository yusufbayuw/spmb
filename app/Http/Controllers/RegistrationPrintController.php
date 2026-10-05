<?php

namespace App\Http\Controllers;

use App\Models\PaymentReceipt;
use App\Models\Registration;
use App\Models\TestBooking;
use App\Models\UnitConfiguration;
use App\Services\ApplicantFileStorage;
use App\Services\RegistrationCardService;
use App\Services\TestCardEligibilityService;
use App\Services\UnitConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RegistrationPrintController extends Controller
{
    private function authorizeRegistration(Request $request, Registration $registration): void
    {
        $user = $request->user();

        abort_unless(
            $user?->is_active
            && ($user->id === $registration->user_id || $user->isAdmin() || ($user->isTU() && (int) $user->unit_id === (int) $registration->unit_id)),
            404,
        );

        if (! $user->isAdmin()) {
            $registration->loadMissing('unit');
            abort_unless($registration->unit?->isOperational(), 404);
        }
    }

    public function applicantCard(
        Request $request,
        Registration $registration,
        RegistrationCardService $cards,
    ): View
    {
        $this->authorizeRegistration($request, $registration);
        $registration->loadMissing('configuration');
        abort_unless(
            $registration->isOperational()
                && $registration->registrationCardEnabled()
                && $registration->applicant_card_number,
            404,
        );

        $card = $cards->cardData($registration);

        return view('registration.card', compact('registration', 'card'));
    }

    public function verifyCard(Registration $registration, RegistrationCardService $cards): View
    {
        $registration->loadMissing(['configuration', 'unit', 'opening.studyProgram', 'pathway']);
        abort_unless(
            filled($registration->applicant_card_number)
                && $registration->registrationCardEnabled(),
            404,
        );

        $hasPhoto = $cards->hasIdentityPhoto($registration);
        $isValid = $registration->isOperational() && $hasPhoto;
        $cardType = 'Kartu Pendaftaran';

        return view('registration.card-verification', compact('registration', 'hasPhoto', 'isValid', 'cardType'));
    }

    public function verifyTestCard(
        Registration $registration,
        RegistrationCardService $cards,
        TestCardEligibilityService $eligibility,
    ): View
    {
        $registration->loadMissing(['configuration', 'unit', 'opening.studyProgram', 'pathway']);
        abort_unless($eligibility->canPrint($registration), 404);

        $hasPhoto = $cards->hasIdentityPhoto($registration);
        $isValid = $registration->isOperational() && $hasPhoto;
        $cardType = 'Kartu Tes';

        return view('registration.card-verification', compact('registration', 'hasPhoto', 'isValid', 'cardType'));
    }

    public function testCard(
        Request $request,
        Registration $registration,
        TestCardEligibilityService $eligibility,
        RegistrationCardService $cards,
    ): View {
        $this->authorizeRegistration($request, $registration);
        abort_unless($registration->isOperational() && $eligibility->canPrint($registration), 404);

        $bookings = TestBooking::with(['session', 'admissionTest'])
            ->where('registration_id', $registration->id)
            ->whereNotNull('test_session_id')
            ->get()
            ->sortBy(function (TestBooking $booking): int {
                $startsAt = $booking->session?->starts_at;

                return filled($startsAt)
                    ? Carbon::parse($startsAt)->timestamp
                    : PHP_INT_MAX;
            })
            ->values();

        abort_if($bookings->isEmpty(), 404);

        $card = $cards->cardData($registration, 'registration.test-card.verify');
        $card['headerLabel'] = 'KARTU TES';
        $card['issuedDate'] = $registration->test_schedule_confirmed_at?->format('d-m-Y') ?? '—';
        $card['filenameStem'] = 'kartu-tes-'.Str::slug(
            $card['cardNumber'] !== '—'
                ? $card['cardNumber']
                : (string) $registration->uuid
        );

        $configuredTests = collect($registration->configuredTests());
        $schedules = $bookings->map(function (TestBooking $booking) use ($configuredTests): array {
            $configuredTest = $configuredTests->first(
                fn (array $test): bool => (int) ($test['id'] ?? 0) === (int) $booking->admission_test_id,
            );
            $session = $booking->session;
            $startsAt = Carbon::parse($session->starts_at);
            $endsAt = Carbon::parse($session->ends_at);

            return [
                'name' => Str::limit((string) ($configuredTest['name'] ?? $booking->admissionTest?->name ?? 'Tes'), 46),
                'time' => $startsAt->format('d/m/Y H:i').'–'.$endsAt->format('H:i'),
                'location' => Str::limit((string) ($session->location ?: 'Lokasi belum ditentukan'), 40),
            ];
        });

        return view('registration.test-card', compact('registration', 'card', 'schedules'));
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

    public function formFieldTemplate(
        Request $request,
        UnitConfiguration $configuration,
        string $key,
        ApplicantFileStorage $storage,
        UnitConfigurationService $configurations,
    ): BinaryFileResponse {
        $user = $request->user();
        abort_unless($user?->is_active, 404);

        if ($user->isUser()) {
            abort_unless(
                $configuration->status === 'published'
                && $configurations->current((int) $configuration->unit_id)?->id === $configuration->id,
                404,
            );
        } elseif ($user->isTU()) {
            abort_unless((int) $user->unit_id === (int) $configuration->unit_id, 404);
        } else {
            abort_unless($user->isAdmin(), 404);
        }

        $field = collect($configuration->fields ?? [])->first(
            fn (array $field): bool => ($field['key'] ?? null) === $key
                && ($field['type'] ?? null) === 'file'
                && (bool) ($field['active'] ?? false),
        );

        $path = $field['template_path'] ?? null;
        abort_unless(
            is_string($path)
            && str_starts_with($path, 'templates/'.$configuration->unit_id.'/')
            && $storage->privateDisk()->exists($path),
            404,
        );

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $downloadName = Str::slug((string) ($field['label'] ?? $key))
            .($extension !== '' ? '.'.$extension : '');

        return response()->download(
            $storage->privateDisk()->path($path),
            $downloadName,
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
