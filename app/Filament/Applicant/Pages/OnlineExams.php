<?php

namespace App\Filament\Applicant\Pages;

use App\Models\OnlineExamAssignment;
use App\Services\OnlineExamEngine;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class OnlineExams extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationLabel = 'Tes Online';
    protected static ?string $title = 'Tes Online';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'ujian-online';
    protected static string $view = 'filament.applicant.pages.online-exams';

    protected function getViewData(): array
    {
        return [
            'assignments' => OnlineExamAssignment::query()
                ->where('user_id', auth()->id())
                ->where('is_active', true)
                ->whereHas('exam.unit', fn (Builder $q) => $q->operational())
                ->with(['exam.unit', 'attempts' => fn ($q) => $q->latest('id')])
                ->latest('id')->get(),
        ];
    }

    public function startExam(string $uuid): void
    {
        $assignment = OnlineExamAssignment::query()
            ->where('uuid', $uuid)->where('user_id', auth()->id())
            ->firstOrFail();

        $attempt = app(OnlineExamEngine::class)->start($assignment, auth()->user());

        $this->redirect(OnlineExamTake::getUrl(['attempt' => $attempt->uuid]));
    }
}
