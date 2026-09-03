<?php

namespace App\Http\Controllers;

use App\Models\RegistrationOpening;
use App\Models\Unit;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $schoolUnits = Unit::query()
            ->where('is_active', true)
            ->whereIn('institution_type', ['early_childhood', 'school'])
            ->orderBy('id')
            ->get();

        $universities = Unit::query()
            ->where('is_active', true)
            ->where('institution_type', 'university')
            ->with(['studyPrograms' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('name')
            ->get();

        $openOfferings = RegistrationOpening::query()
            ->where('status', 'open')
            ->with(['unit', 'studyProgram'])
            ->orderBy('unit_id')
            ->orderBy('study_program_id')
            ->get();

        return view('welcome', compact('schoolUnits', 'universities', 'openOfferings'));
    }
}
