<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class PublicUnitAdmissionsController extends Controller
{
    public function __invoke(Unit $unit): View
    {
        abort_unless($unit->is_active, 404);

        $openings = $unit->registrationOpenings()
            ->visibleToApplicants()
            ->currentlyOpen()
            ->with(['unit', 'studyProgram'])
            ->orderByRaw('CASE WHEN closed_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('closed_at')
            ->orderBy('study_program_id')
            ->orderBy('wave')
            ->get();

        $upcomingOpenings = $unit->registrationOpenings()
            ->visibleToApplicants()
            ->upcoming()
            ->with(['unit', 'studyProgram'])
            ->orderBy('opened_at')
            ->orderBy('study_program_id')
            ->orderBy('wave')
            ->get();

        $recentClosedOpenings = collect();
        if ($openings->isEmpty() && $upcomingOpenings->isEmpty()) {
            $recentClosedOpenings = $unit->registrationOpenings()
                ->visibleToApplicants()
                ->with(['unit', 'studyProgram'])
                ->where(function (Builder $query): void {
                    $query
                        ->where(fn (Builder $scheduled): Builder => $scheduled->whereNotNull('closed_at')->where('closed_at', '<=', now()))
                        ->orWhere(fn (Builder $legacy): Builder => $legacy->where('status', 'closed')->where(fn (Builder $schedule): Builder => $schedule->whereNull('opened_at')->orWhereNull('closed_at')));
                })
                ->orderByDesc('closed_at')
                ->limit(3)
                ->get();
        }

        return view('admissions.unit', [
            'unit' => $unit,
            'openings' => $openings,
            'upcomingOpenings' => $upcomingOpenings,
            'recentClosedOpenings' => $recentClosedOpenings,
        ]);
    }
}
