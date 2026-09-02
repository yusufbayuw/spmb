<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Services\AuditTrail;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $roles = $this->record->roles()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        if ($roles === []) {
            return;
        }

        app(AuditTrail::class)->record(
            'user.roles_assigned',
            $this->record,
            newValues: ['roles' => $roles],
            description: 'Role awal pengguna ditetapkan',
        );
    }
}
