<?php

namespace App\Filament\Applicant\Resources\RegistrationResource\Pages;

use App\Filament\Applicant\Pages\RegistrationOpenings;
use App\Filament\Applicant\Resources\RegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

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
