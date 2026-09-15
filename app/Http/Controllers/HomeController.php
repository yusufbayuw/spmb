<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $selectedCategory = $request->string('kategori')->toString() === 'universitas' ? 'universitas' : 'sekolah';
        $selectedUnitCode = trim($request->string('unit')->toString()) ?: null;
        $selectedDegree = trim($request->string('jenjang')->toString()) ?: null;

        $schoolUnits = Unit::query()
            ->where('is_active', true)
            ->whereIn('institution_type', ['early_childhood', 'school'])
            ->orderByRaw("CASE code WHEN 'DC' THEN 1 WHEN 'KB' THEN 2 WHEN 'TK' THEN 3 WHEN 'SD' THEN 4 WHEN 'SMP' THEN 5 WHEN 'SMA' THEN 6 ELSE 99 END")
            ->orderBy('name')
            ->get();

        $universities = Unit::query()
            ->where('is_active', true)
            ->where('institution_type', 'university')
            ->with(['studyPrograms' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('name')
            ->get();

        $openOfferings = $this->applyPublicFilters(
            RegistrationOpening::query()->currentlyOpen()->with(['unit', 'studyProgram']),
            $selectedCategory,
            $selectedUnitCode,
            $selectedDegree,
        )
            ->orderBy('closed_at')
            ->orderBy('unit_id')
            ->orderBy('study_program_id')
            ->get();

        $upcomingOfferings = $this->applyPublicFilters(
            RegistrationOpening::query()->upcoming()->with(['unit', 'studyProgram']),
            $selectedCategory,
            $selectedUnitCode,
            $selectedDegree,
        )
            ->orderBy('opened_at')
            ->orderBy('unit_id')
            ->orderBy('study_program_id')
            ->get();

        $registrationPreviews = collect();
        $user = $request->user();

        if ($user?->isUser()) {
            $registrationPreviews = Registration::query()
                ->where('user_id', $user->id)
                ->with(['unit', 'opening.studyProgram', 'pathway'])
                ->latest()
                ->limit(3)
                ->get();
        }

        $academicYears = $openOfferings
            ->concat($upcomingOfferings)
            ->pluck('academic_year')
            ->filter()
            ->unique()
            ->values();

        $headlineAcademicYear = $academicYears->count() === 1 ? $academicYears->first() : null;
        $degreeOptions = $universities
            ->flatMap->studyPrograms
            ->pluck('degree_level')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('welcome', compact(
            'schoolUnits',
            'universities',
            'openOfferings',
            'upcomingOfferings',
            'registrationPreviews',
            'headlineAcademicYear',
            'selectedCategory',
            'selectedUnitCode',
            'selectedDegree',
            'degreeOptions',
        ));
    }

    private function applyPublicFilters(
        Builder $query,
        string $category,
        ?string $unitCode,
        ?string $degree,
    ): Builder {
        $query->whereHas('unit', function (Builder $unitQuery) use ($category): void {
            $category === 'universitas'
                ? $unitQuery->where('institution_type', 'university')
                : $unitQuery->whereIn('institution_type', ['early_childhood', 'school']);
        });

        if ($unitCode) {
            $query->whereHas('unit', fn (Builder $unitQuery): Builder => $unitQuery->where('code', $unitCode));
        }

        if ($category === 'universitas' && $degree) {
            $query->whereHas('studyProgram', fn (Builder $programQuery): Builder => $programQuery->where('degree_level', $degree));
        }

        return $query;
    }
}
