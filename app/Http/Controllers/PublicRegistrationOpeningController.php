<?php

namespace App\Http\Controllers;

use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicRegistrationOpeningController extends Controller
{
    public function __invoke(Request $request, RegistrationOpening $registrationOpening): View
    {
        abort_unless(
            RegistrationOpening::query()
                ->visibleToApplicants()
                ->whereKey($registrationOpening->getKey())
                ->exists(),
            404,
        );

        $registrationOpening->load(['unit', 'studyProgram']);

        $pathways = RegistrationPathway::query()
            ->availableForUnit((int) $registrationOpening->unit_id)
            ->orderBy('name')
            ->get();

        $existingRegistrations = collect();
        if ($request->user()?->isUser()) {
            $existingRegistrations = $request->user()
                ->registrations()
                ->where('registration_opening_id', $registrationOpening->id)
                ->latest()
                ->get();
        }

        return view('admissions.show', [
            'opening' => $registrationOpening,
            'pathways' => $pathways,
            'existingRegistrations' => $existingRegistrations,
        ]);
    }
}
