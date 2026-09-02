<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Registration;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class RegistrationStatus extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Status Pendaftaran';
    protected static ?string $slug = 'status/{registration}';
    protected static string $view = 'filament.applicant.pages.registration-status';

    public Registration $registrationRecord;

    public function mount(int|string $registration): void
    {
        $this->registrationRecord = Registration::query()
            ->where('user_id', auth()->id())
            ->with([
                'unit',
                'parentInfo',
                'documents',
                'latestPayment',
                'testResults.admissionTest',
                'selection',
                'announcement',
            ])
            ->findOrFail($registration);
    }

    public function getTitle(): string
    {
        return $this->registrationRecord->full_name;
    }

    public function getSubheading(): ?string
    {
        return ($this->registrationRecord->registration_number ?? 'Pendaftaran').' · '.($this->registrationRecord->unit?->name ?? 'Unit');
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::SevenExtraLarge;
    }

    public function stageIndex(): int
    {
        $index = array_search($this->registrationRecord->current_stage, Registration::STAGES, true);

        return $index === false ? 0 : $index;
    }
}
