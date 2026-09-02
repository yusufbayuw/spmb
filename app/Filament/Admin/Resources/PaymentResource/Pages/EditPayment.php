<?php

namespace App\Filament\Admin\Resources\PaymentResource\Pages;

use App\Filament\Admin\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === 'verified') {
            $data['verified_at'] = $this->record->verified_at ?? now();
            $data['verified_by'] = $this->record->verified_by ?? auth()->id();
        } else {
            $data['verified_at'] = null;
            $data['verified_by'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->visible(fn () => auth()->user()?->isAdmin() ?? false),
        ];
    }
}
