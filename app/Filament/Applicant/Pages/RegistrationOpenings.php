<?php

namespace App\Filament\Applicant\Pages;

use App\Models\EducationLevel;
use App\Models\RegistrationOpening;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;

class RegistrationOpenings extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Pendaftaran Dibuka';

    protected static ?string $title = 'Pilih Pendaftaran';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'pendaftaran';

    protected static string $view = 'filament.applicant.pages.registration-openings';

    public string $search = '';

    public ?string $educationLevelCode = null;

    public string $availability = 'open';

    public array $educationLevelOptions = [];

    public function mount(): void
    {
        $this->educationLevelOptions = EducationLevel::query()
            ->active()
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('units.registrationOpenings', fn (Builder $openingQuery): Builder => $openingQuery->visibleToApplicants())
                    ->orWhereHas('studyPrograms.registrationOpenings', fn (Builder $openingQuery): Builder => $openingQuery->visibleToApplicants());
            })
            ->ordered()
            ->pluck('code', 'code')
            ->all();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEducationLevelCode(): void
    {
        if ($this->educationLevelCode && ! array_key_exists($this->educationLevelCode, $this->educationLevelOptions)) {
            $this->educationLevelCode = null;
        }

        $this->resetPage();
    }

    public function selectEducationLevel(?string $educationLevelCode = null): void
    {
        $this->educationLevelCode = $educationLevelCode && array_key_exists($educationLevelCode, $this->educationLevelOptions)
            ? $educationLevelCode
            : null;
        $this->resetPage();
    }

    public function selectAvailability(string $availability): void
    {
        $this->availability = in_array($availability, ['open', 'scheduled', 'all'], true)
            ? $availability
            : 'open';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'educationLevelCode']);
        $this->availability = 'open';
        $this->resetPage();
    }

    public function getOpeningsProperty(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $dateColumn = $this->availability === 'scheduled' ? 'opened_at' : 'closed_at';

        return RegistrationOpening::query()
            ->select('registration_openings.*')
            ->leftJoin('units', 'units.id', '=', 'registration_openings.unit_id')
            ->leftJoin('education_levels as unit_levels', 'unit_levels.id', '=', 'units.education_level_id')
            ->leftJoin('study_programs', 'study_programs.id', '=', 'registration_openings.study_program_id')
            ->leftJoin('education_levels as program_levels', 'program_levels.id', '=', 'study_programs.education_level_id')
            ->visibleToApplicants()
            ->with(['unit.educationLevel', 'studyProgram.educationLevel'])
            ->when(filled($this->educationLevelCode), function (Builder $query): Builder {
                $levelCode = $this->educationLevelCode;

                return $query->where(function (Builder $levelQuery) use ($levelCode): void {
                    $levelQuery
                        ->where(function (Builder $schoolQuery) use ($levelCode): void {
                            $schoolQuery
                                ->whereNull('registration_openings.study_program_id')
                                ->where('unit_levels.code', $levelCode);
                        })
                        ->orWhere('program_levels.code', $levelCode);
                });
            })
            ->when(filled($search), function (Builder $query) use ($search): Builder {
                $term = '%'.$search.'%';

                return $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery
                        ->where('registration_openings.academic_year', 'like', $term)
                        ->orWhere('registration_openings.wave', 'like', $term)
                        ->orWhere('units.name', 'like', $term)
                        ->orWhere('units.code', 'like', $term)
                        ->orWhere('unit_levels.code', 'like', $term)
                        ->orWhere('unit_levels.name', 'like', $term)
                        ->orWhere('study_programs.name', 'like', $term)
                        ->orWhere('study_programs.code', 'like', $term)
                        ->orWhere('study_programs.faculty', 'like', $term)
                        ->orWhere('program_levels.code', 'like', $term)
                        ->orWhere('program_levels.name', 'like', $term);
                });
            })
            ->when($this->availability === 'open', fn (Builder $query): Builder => $query->currentlyOpen())
            ->when($this->availability === 'scheduled', fn (Builder $query): Builder => $query
                ->whereNotNull('registration_openings.opened_at')
                ->where('registration_openings.opened_at', '>', now()))
            ->orderByRaw('COALESCE(program_levels.sort_order, unit_levels.sort_order, 65535)')
            ->orderByRaw('COALESCE(study_programs.sort_order, 0)')
            ->orderBy('units.name')
            ->orderByRaw("CASE WHEN registration_openings.{$dateColumn} IS NULL THEN 1 ELSE 0 END")
            ->orderBy("registration_openings.{$dateColumn}")
            ->orderBy('registration_openings.wave')
            ->orderBy('registration_openings.id')
            ->paginate(12);
    }

    protected function getViewData(): array
    {
        return ['openings' => $this->openings];
    }

    public function getSubheading(): ?string
    {
        return 'Pilih jenjang pendidikan atau program studi tujuan.';
    }
}
