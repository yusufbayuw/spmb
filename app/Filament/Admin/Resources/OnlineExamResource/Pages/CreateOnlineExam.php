<?php

namespace App\Filament\Admin\Resources\OnlineExamResource\Pages;

use App\Filament\Admin\Resources\OnlineExamResource;
use App\Services\OnlineExamAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateOnlineExam extends CreateRecord
{
    protected static string $resource = OnlineExamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        if ($actor?->isAdminUnit()) {
            $data['unit_id'] = $actor->unit_id;
        }

        abort_unless(app(OnlineExamAccess::class)->canManageUnit($actor, (int) ($data['unit_id'] ?? 0)), 403);

        return $data;
    }
}
