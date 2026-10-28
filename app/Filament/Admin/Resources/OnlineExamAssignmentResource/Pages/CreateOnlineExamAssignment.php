<?php

namespace App\Filament\Admin\Resources\OnlineExamAssignmentResource\Pages;

use App\Filament\Admin\Resources\OnlineExamAssignmentResource;
use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\User;
use App\Services\OnlineExamEngine;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOnlineExamAssignment extends CreateRecord
{
    protected static string $resource = OnlineExamAssignmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(OnlineExamEngine::class)->assign(
            OnlineExam::query()->findOrFail($data['online_exam_id'] ?? 0),
            User::query()->findOrFail($data['user_id'] ?? 0),
            auth()->user(),
        );
    }
}
