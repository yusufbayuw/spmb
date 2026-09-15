<?php

namespace App\Filament\Applicant\Pages;

use App\Models\RegistrationOpening;
use App\Models\Unit;
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

    public ?string $unitUuid = null;

    public string $availability = 'open';

    public array $unitOptions = [];

    public function mount(): void
    {
        $this->unitOptions = Unit::query()
            ->whereHas('registrationOpenings', fn (Builder $query): Builder => $query->visibleToApplicants())
            ->orderByRaw("CASE code WHEN 'DAYCARE' THEN 1 WHEN 'KB' THEN 2 WHEN 'TK' THEN 3 WHEN 'SD' THEN 4 WHEN 'SMP' THEN 5 WHEN 'SMA' THEN 6 ELSE 99 END")
            ->orderBy('name')
            ->pluck('name', 'uuid')
            ->all();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUnitUuid(): void
    {
        $this->resetPage();
    }

    public function selectUnit(?string $unitUuid = null): void
    {
        $this->unitUuid = $unitUuid && array_key_exists($unitUuid, $this->unitOptions)
            ? $unitUuid
            : null;
        $this->resetPage();
    }

    public function updatedAvailability(): void
    {
        if (! in_array($this->availability, ['all', 'open', 'scheduled', 'closed'], true)) {
            $this->availability = 'open';
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'unitUuid']);
        $this->availability = 'open';
        $this->resetPage();
    }

    public function getOpeningsProperty(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return RegistrationOpening::query()
            ->visibleToApplicants()
            ->with(['unit', 'studyProgram'])
            ->when(filled($this->unitUuid), fn (Builder $query): Builder => $query->whereHas('unit', fn (Builder $unitQuery): Builder => $unitQuery->where('uuid', $this->unitUuid)))
            ->when(filled($search), function (Builder $query) use ($search): Builder {
                $term = '%'.$search.'%';

                return $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery
                        ->where('academic_year', 'like', $term)
                        ->orWhere('wave', 'like', $term)
                        ->orWhereHas('unit', fn (Builder $unitQuery): Builder => $unitQuery->where('name', 'like', $term)->orWhere('code', 'like', $term))
                        ->orWhereHas('studyProgram', fn (Builder $programQuery): Builder => $programQuery->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('faculty', 'like', $term));
                });
            })
            ->when($this->availability === 'open', fn (Builder $query): Builder => $query->currentlyOpen())
            ->when($this->availability === 'scheduled', fn (Builder $query): Builder => $query->whereNotNull('opened_at')->where('opened_at', '>', now()))
            ->when($this->availability === 'closed', function (Builder $query): Builder {
                return $query->where(function (Builder $statusQuery): void {
                    $statusQuery
                        ->where(fn (Builder $scheduledQuery): Builder => $scheduledQuery->whereNotNull('closed_at')->where('closed_at', '<=', now()))
                        ->orWhere(fn (Builder $legacyQuery): Builder => $legacyQuery->where('status', 'closed')->where(fn (Builder $scheduleQuery): Builder => $scheduleQuery->whereNull('opened_at')->orWhereNull('closed_at')));
                });
            })
            ->orderByRaw("CASE WHEN closed_at IS NULL THEN 1 ELSE 0 END")
            ->orderBy('closed_at')
            ->orderBy('unit_id')
            ->orderBy('study_program_id')
            ->orderBy('wave')
            ->paginate(12);
    }

    protected function getViewData(): array
    {
        return ['openings' => $this->openings];
    }

    public function getSubheading(): ?string
    {
        return 'Pilih satuan pendidikan atau program studi yang sedang membuka pendaftaran.';
    }
}
