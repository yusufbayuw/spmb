<?php

namespace App\Filament\Admin\Resources\OnlineExamItemResource\Pages;

use App\Filament\Admin\Resources\OnlineExamItemResource;
use App\Models\OnlineExam;
use App\Services\OnlineExamAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateOnlineExamItem extends CreateRecord
{
    protected static string $resource = OnlineExamItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $exam = OnlineExam::query()->findOrFail($data['online_exam_id'] ?? 0);
        abort_unless(app(OnlineExamAccess::class)->canManage(auth()->user(), $exam), 403);

        return $data;
    }
}
