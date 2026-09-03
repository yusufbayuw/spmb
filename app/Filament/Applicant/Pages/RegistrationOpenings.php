<?php

namespace App\Filament\Applicant\Pages;

use App\Models\RegistrationOpening;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class RegistrationOpenings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Pendaftaran Dibuka';
    protected static ?string $title = 'Pilih Pendaftaran';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'pendaftaran';
    protected static string $view = 'filament.applicant.pages.registration-openings';

    public Collection $openings;

    public function mount(): void
    {
        $this->openings = RegistrationOpening::query()
            ->visibleToApplicants()
            ->with(['unit', 'studyProgram'])
            ->orderByDesc('academic_year')
            ->orderBy('unit_id')
            ->orderBy('study_program_id')
            ->orderBy('wave')
            ->get();
    }

    public function getSubheading(): ?string
    {
        return 'Pilih satuan pendidikan atau program studi, tahun ajaran/akademik, gelombang, dan jalur yang tersedia.';
    }
}
