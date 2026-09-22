<?php

namespace App\Http\Controllers;

use App\Models\EducationLevel;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Support\SpmbOperationalMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $educationLevels = EducationLevel::query()
            ->forOperationalMode()
            ->active()
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('units', fn (Builder $unitQuery): Builder => $unitQuery
                        ->where('is_active', true)
                        ->whereIn('institution_type', ['early_childhood', 'school']))
                    ->orWhereHas('studyPrograms', fn (Builder $programQuery): Builder => $programQuery
                        ->where('is_active', true)
                        ->whereHas('unit', fn (Builder $unitQuery): Builder => $unitQuery
                            ->where('is_active', true)
                            ->where('institution_type', 'university')));
            })
            ->ordered()
            ->get();

        $selectedLevelCode = mb_strtoupper(trim($request->string('jenjang')->toString())) ?: null;

        // Backward compatibility for old public links such as ?kategori=sekolah&unit=SD.
        if (! $selectedLevelCode) {
            $selectedLevelCode = mb_strtoupper(trim($request->string('unit')->toString())) ?: null;
        }

        if ($selectedLevelCode && ! $educationLevels->contains('code', $selectedLevelCode)) {
            $selectedLevelCode = null;
        }

        $openOfferings = $this->sortOfferings(
            $this->applyPublicFilters(
                RegistrationOpening::query()
                    ->currentlyOpen()
                    ->with(['unit.educationLevel', 'studyProgram.educationLevel']),
                $selectedLevelCode,
            )->get(),
            'closed_at',
        );

        $upcomingOfferings = $this->sortOfferings(
            $this->applyPublicFilters(
                RegistrationOpening::query()
                    ->upcoming()
                    ->with(['unit.educationLevel', 'studyProgram.educationLevel']),
                $selectedLevelCode,
            )->get(),
            'opened_at',
        );

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
        $operationalProfile = SpmbOperationalMode::profile();
        $helpdeskUnits = collect();

        if (SpmbOperationalMode::isHigherEducation()) {
            $helpdeskUnits = $openOfferings
                ->concat($upcomingOfferings)
                ->pluck('unit')
                ->filter()
                ->unique('id')
                ->values();

            if ($helpdeskUnits->isEmpty()) {
                $helpdeskUnits = Unit::query()
                    ->forOperationalMode()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }
        }

        return view('welcome', compact(
            'educationLevels',
            'openOfferings',
            'upcomingOfferings',
            'registrationPreviews',
            'headlineAcademicYear',
            'selectedLevelCode',
            'operationalProfile',
            'helpdeskUnits',
        ));
    }

    private function applyPublicFilters(Builder $query, ?string $levelCode): Builder
    {
        if (! $levelCode) {
            return $query;
        }

        return $query->where(function (Builder $offeringQuery) use ($levelCode): void {
            $offeringQuery
                ->where(function (Builder $schoolQuery) use ($levelCode): void {
                    $schoolQuery
                        ->whereNull('study_program_id')
                        ->whereHas('unit.educationLevel', fn (Builder $levelQuery): Builder => $levelQuery->where('code', $levelCode));
                })
                ->orWhereHas('studyProgram.educationLevel', fn (Builder $levelQuery): Builder => $levelQuery->where('code', $levelCode));
        });
    }

    private function sortOfferings(Collection $offerings, string $dateColumn): Collection
    {
        return $offerings
            ->sort(function (RegistrationOpening $left, RegistrationOpening $right) use ($dateColumn): int {
                $leftLevel = $left->studyProgram?->educationLevel ?? $left->unit?->educationLevel;
                $rightLevel = $right->studyProgram?->educationLevel ?? $right->unit?->educationLevel;

                $comparison = ($leftLevel?->sort_order ?? PHP_INT_MAX) <=> ($rightLevel?->sort_order ?? PHP_INT_MAX);

                if ($comparison !== 0) {
                    return $comparison;
                }

                $leftDate = $left->{$dateColumn}?->getTimestamp() ?? PHP_INT_MAX;
                $rightDate = $right->{$dateColumn}?->getTimestamp() ?? PHP_INT_MAX;
                $comparison = $leftDate <=> $rightDate;

                if ($comparison !== 0) {
                    return $comparison;
                }

                $comparison = strcmp((string) $left->unit?->name, (string) $right->unit?->name);

                if ($comparison !== 0) {
                    return $comparison;
                }

                $comparison = ($left->studyProgram?->sort_order ?? 0) <=> ($right->studyProgram?->sort_order ?? 0);

                if ($comparison !== 0) {
                    return $comparison;
                }

                return ($left->id ?? 0) <=> ($right->id ?? 0);
            })
            ->values();
    }
}
