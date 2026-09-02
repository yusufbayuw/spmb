<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Services\AuditTrail;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $rolesBeforeSave = [];

    protected function beforeSave(): void
    {
        $this->rolesBeforeSave = $this->record->roles()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    protected function afterSave(): void
    {
        $rolesAfterSave = $this->record->roles()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        if ($this->rolesBeforeSave === $rolesAfterSave) {
            return;
        }

        app(AuditTrail::class)->record(
            'user.roles_changed',
            $this->record,
            oldValues: ['roles' => $this->rolesBeforeSave],
            newValues: ['roles' => $rolesAfterSave],
            description: 'Role pengguna diubah',
        );
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
