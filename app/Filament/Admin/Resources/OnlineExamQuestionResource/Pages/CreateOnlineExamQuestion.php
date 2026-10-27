<?php

namespace App\Filament\Admin\Resources\OnlineExamQuestionResource\Pages;

use App\Filament\Admin\Resources\OnlineExamQuestionResource;
use App\Services\OnlineExamAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateOnlineExamQuestion extends CreateRecord
{
    protected static string $resource = OnlineExamQuestionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        if ($actor?->isAdminUnit()) { $data['unit_id'] = $actor->unit_id; }
        abort_unless(app(OnlineExamAccess::class)->canManageUnit($actor, (int) ($data['unit_id'] ?? 0)), 403);

        return $data;
    }
}
