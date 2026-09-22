<?php

namespace App\Http\Controllers;

use App\Models\Faq;
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

        $faqs = Faq::query()
            ->publiclyVisible()
            ->where('unit_id', $registrationOpening->unit_id)
            ->where(function ($query) use ($registrationOpening): void {
                $query->whereNull('study_program_id');

                if ($registrationOpening->study_program_id) {
                    $query->orWhere('study_program_id', $registrationOpening->study_program_id);
                }
            })
            ->where(function ($query) use ($pathways): void {
                $query->whereNull('registration_pathway_id');

                if ($pathways->isNotEmpty()) {
                    $query->orWhereIn('registration_pathway_id', $pathways->pluck('id'));
                }
            })
            ->with(['studyProgram', 'registrationPathway'])
            ->ordered()
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
            'faqs' => $faqs,
            'existingRegistrations' => $existingRegistrations,
        ]);
    }
}
