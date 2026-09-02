<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Filament\Admin\Resources\RegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $status = $data['status'] ?? null;

        if ($status === 'submitted' && blank($data['submitted_at'] ?? null)) {
            $data['submitted_at'] = now();
        }

        if ($status === 'verified' && blank($data['verified_at'] ?? null)) {
            $data['verified_at'] = now();
        }

        if ($status === 'payment_verified' && blank($data['payment_verified_at'] ?? null)) {
            $data['payment_verified_at'] = now();
        }

        if ($status === 'accepted' && blank($data['accepted_at'] ?? null)) {
            $data['accepted_at'] = now();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()?->isAdmin() ?? false),
        ];
    }
}
