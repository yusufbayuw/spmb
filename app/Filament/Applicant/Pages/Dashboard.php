<?php

namespace App\Filament\Applicant\Pages;

use App\Models\Registration;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Beranda';
    protected static ?string $title = 'Beranda Pendaftar';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.applicant.pages.dashboard';

    public Collection $registrations;

    public function mount(): void
    {
        $this->registrations = Registration::query()
            ->where('user_id', auth()->id())
            ->with(['unit', 'latestPayment', 'selection', 'announcement'])
            ->latest()
            ->get();
    }

    public function getHeading(): string
    {
        return 'Halo, '.auth()->user()->name;
    }

    public function getSubheading(): ?string
    {
        return 'Pantau seluruh proses pendaftaran calon siswa dari satu halaman.';
    }
}
