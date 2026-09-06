<?php

namespace App\Filament\Applicant\Resources\RegistrationResource\Pages;

use App\Filament\Applicant\Pages\RegistrationOpenings;
use App\Filament\Applicant\Resources\RegistrationResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'active' => Tab::make('Aktif')->query(fn (Builder $query): Builder => $query->where('lifecycle_status', 'active')),
            'withdrawn' => Tab::make('Mengundurkan Diri')->query(fn (Builder $query): Builder => $query->where('lifecycle_status', 'withdrawn')),
            'cancelled' => Tab::make('Dibatalkan')->query(fn (Builder $query): Builder => $query->where('lifecycle_status', 'cancelled')),
            'archived' => Tab::make('Diarsipkan')->query(fn (Builder $query): Builder => $query->where('lifecycle_status', 'archived')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('chooseOpening')
                ->label('Daftarkan Calon Siswa')
                ->icon('heroicon-o-plus')
                ->url(RegistrationOpenings::getUrl()),
        ];
    }

    public function getTitle(): string
    {
        return 'Pendaftaran Saya';
    }

    public function getSubheading(): ?string
    {
        return 'Kelola seluruh calon siswa yang didaftarkan melalui akun ini.';
    }
}
