<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Registration;
use App\Services\ApplicantNextActionService;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Str;

class RegistrationStatus extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Status Pendaftaran';

    protected static ?string $slug = 'status/{registration}';

    protected static string $view = 'filament.applicant.pages.registration-status';

    public Registration $registrationRecord;

    /** @var array<string, mixed>|null */
    public ?array $nextAction = null;

    public function mount(int|string $registration): void
    {
        abort_unless(Str::isUuid($registration), 404);
        $this->registrationRecord = Registration::query()
            ->where('user_id', auth()->id())
            ->whereHas('unit', fn ($unitQuery) => $unitQuery->operational())
            ->with([
                'configuration',
                'receipts',
                'unit',
                'opening.studyProgram',
                'parentInfo',
                'documents',
                'latestPayment.virtualAccount',
                'testResults.admissionTest.studyProgram',
                'testBookings.session',
                'selection',
                'announcement',
                'admissionOffer',
                'reRegistrationItems',
            ])
            ->where('uuid', $registration)->firstOrFail();

        // Counts are scoped to this applicant's already-authorized opening.
        // Only query statistics when explicitly enabled by its Admin Unit.
        $opening = $this->registrationRecord->opening;
        if ($opening && ($opening->show_total_applicants || $opening->show_verified_applicants)) {
            $this->registrationRecord->setRelation(
                'opening',
                $this->registrationRecord->opening()->withApplicantStatistics()->firstOrFail(),
            );
        }

        $this->nextAction = app(ApplicantNextActionService::class)->resolve($this->registrationRecord);
    }

    public function getTitle(): string
    {
        return $this->registrationRecord->full_name;
    }

    public function getSubheading(): ?string
    {
        $parts = [
            $this->registrationRecord->registration_number ?? 'Pendaftaran',
            $this->registrationRecord->unit?->name ?? 'Unit / Institusi',
        ];

        if ($this->registrationRecord->opening?->studyProgram) {
            $parts[] = $this->registrationRecord->opening->studyProgram->label();
        }

        return implode(' · ', $parts);
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::SevenExtraLarge;
    }

    public function stageIndex(): int
    {
        $visibleStages = array_keys($this->registrationRecord->progressStages());

        if ($this->registrationRecord->current_stage === 'completed') {
            $completedIndex = array_search('completed', $visibleStages, true);

            return $completedIndex === false ? max(0, count($visibleStages) - 1) : $completedIndex;
        }

        $operationalStages = array_keys($this->registrationRecord->enabledStages());
        $currentOperationalIndex = array_search(
            $this->registrationRecord->current_stage,
            $operationalStages,
            true,
        );

        if ($currentOperationalIndex === false) {
            return 0;
        }

        $visibleIndex = 0;

        foreach ($visibleStages as $index => $stage) {
            $operationalIndex = array_search($stage, $operationalStages, true);

            if ($operationalIndex !== false && $operationalIndex <= $currentOperationalIndex) {
                $visibleIndex = $index;
            }
        }

        return $visibleIndex;
    }
}
