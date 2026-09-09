<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Registration;
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

    public function mount(int|string $registration): void
    {
        abort_unless(Str::isUuid($registration), 404);
        $this->registrationRecord = Registration::query()
            ->where('user_id', auth()->id())
            ->with([
                'configuration',
                'receipts',
                'unit',
                'opening.studyProgram',
                'parentInfo',
                'academicScores',
                'achievements',
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
        $index = array_search($this->registrationRecord->current_stage, array_keys($this->registrationRecord->enabledStages()), true);

        return $index === false ? 0 : $index;
    }
}
