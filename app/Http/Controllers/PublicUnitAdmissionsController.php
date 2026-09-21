<?php

namespace App\Http\Controllers;

use App\Models\RegistrationOpening;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicUnitAdmissionsController extends Controller
{
    public function __invoke(Unit $unit): View
    {
        abort_unless($unit->is_active && $unit->isAllowedByOperationalMode(), 404);

        $openings = $this->sortOpenings(
            $unit->registrationOpenings()
                ->visibleToApplicants()
                ->currentlyOpen()
                ->with(['unit.educationLevel', 'studyProgram.educationLevel'])
                ->get(),
            'closed_at',
        );

        $upcomingOpenings = $this->sortOpenings(
            $unit->registrationOpenings()
                ->visibleToApplicants()
                ->upcoming()
                ->with(['unit.educationLevel', 'studyProgram.educationLevel'])
                ->get(),
            'opened_at',
        );

        $recentClosedOpenings = collect();
        if ($openings->isEmpty() && $upcomingOpenings->isEmpty()) {
            $recentClosedOpenings = $this->sortOpenings(
                $unit->registrationOpenings()
                    ->visibleToApplicants()
                    ->with(['unit.educationLevel', 'studyProgram.educationLevel'])
                    ->where(function (Builder $query): void {
                        $query
                            ->where(fn (Builder $scheduled): Builder => $scheduled->whereNotNull('closed_at')->where('closed_at', '<=', now()))
                            ->orWhere(fn (Builder $legacy): Builder => $legacy->where('status', 'closed')->where(fn (Builder $schedule): Builder => $schedule->whereNull('opened_at')->orWhereNull('closed_at')));
                    })
                    ->get(),
                'closed_at',
                true,
            )->take(3)->values();
        }

        return view('admissions.unit', [
            'unit' => $unit,
            'openings' => $openings,
            'upcomingOpenings' => $upcomingOpenings,
            'recentClosedOpenings' => $recentClosedOpenings,
        ]);
    }

    private function sortOpenings(Collection $openings, string $dateColumn, bool $descendingDate = false): Collection
    {
        return $openings
            ->sort(function (RegistrationOpening $left, RegistrationOpening $right) use ($dateColumn, $descendingDate): int {
                $leftLevel = $left->studyProgram?->educationLevel ?? $left->unit?->educationLevel;
                $rightLevel = $right->studyProgram?->educationLevel ?? $right->unit?->educationLevel;

                $comparison = ($leftLevel?->sort_order ?? PHP_INT_MAX) <=> ($rightLevel?->sort_order ?? PHP_INT_MAX);

                if ($comparison !== 0) {
                    return $comparison;
                }

                $leftDate = $left->{$dateColumn}?->getTimestamp();
                $rightDate = $right->{$dateColumn}?->getTimestamp();

                if ($leftDate !== $rightDate) {
                    if ($leftDate === null) {
                        return 1;
                    }

                    if ($rightDate === null) {
                        return -1;
                    }

                    $comparison = $leftDate <=> $rightDate;

                    return $descendingDate ? -$comparison : $comparison;
                }

                $comparison = ($left->studyProgram?->sort_order ?? 0) <=> ($right->studyProgram?->sort_order ?? 0);

                if ($comparison !== 0) {
                    return $comparison;
                }

                $comparison = strcmp((string) $left->studyProgram?->name, (string) $right->studyProgram?->name);

                if ($comparison !== 0) {
                    return $comparison;
                }

                return strcmp((string) $left->wave, (string) $right->wave);
            })
            ->values();
    }
}
