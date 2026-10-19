<?php

namespace App\Http\Controllers;

use App\Filament\Applicant\Resources\RegistrationResource;
use App\Services\PausedOpeningApplicantRedirect;
use App\Models\RegistrationOpening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StartRegistrationController extends Controller
{
    public function __invoke(Request $request, RegistrationOpening $registrationOpening): RedirectResponse|View
    {
        // Public cards and details remain hidden. Only owners of already-saved
        // registrations can follow an old application URL while paused.
        if ($registrationOpening->isPaused()) {
            $target = app(PausedOpeningApplicantRedirect::class)
                ->url($registrationOpening->loadMissing('unit'), $request->user());

            abort_unless($target, 404);

            return redirect()->to($target);
        }

        abort_unless(
            RegistrationOpening::query()
                ->visibleToApplicants()
                ->whereKey($registrationOpening->getKey())
                ->exists(),
            404,
        );

        $registrationOpening->load(['unit', 'studyProgram']);

        if (! $registrationOpening->isOpen()) {
            return redirect()
                ->route('admissions.show', $registrationOpening)
                ->with('admission_notice', $registrationOpening->operationalStatus() === 'scheduled'
                    ? 'Pendaftaran ini belum dibuka.'
                    : 'Pendaftaran ini sudah ditutup.');
        }

        $createUrl = RegistrationResource::getUrl(
            'create',
            ['opening' => $registrationOpening->uuid],
            panel: 'pendaftar',
        );

        $user = $request->user();
        if (! $user) {
            $request->session()->put('url.intended', $createUrl);

            return redirect('/pendaftar/register');
        }

        if (! $user->isUser()) {
            return redirect('/admin');
        }

        if (! $user->hasVerifiedEmail()) {
            $request->session()->put('url.intended', $createUrl);

            return redirect('/pendaftar');
        }

        $existingRegistrations = $user->registrations()
            ->where('registration_opening_id', $registrationOpening->id)
            ->latest()
            ->get();

        if ($existingRegistrations->isNotEmpty() && ! $request->boolean('new')) {
            return view('admissions.start', [
                'opening' => $registrationOpening,
                'existingRegistrations' => $existingRegistrations,
            ]);
        }

        return redirect()->to($createUrl);
    }
}
