<?php

namespace App\Filament\Admin\Resources\OnlineExamAttemptResource\Pages;

use App\Filament\Admin\Resources\OnlineExamAttemptResource;
use App\Models\OnlineExam;
use App\Services\OnlineExamAccess;
use App\Services\OnlineExamMonitoringService;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class MonitorOnlineExams extends Page
{
    protected static string $resource = OnlineExamAttemptResource::class;

    protected static string $view = 'filament.admin.resources.online-exam-attempts.monitor';

    protected static ?string $title = 'Dashboard Pengawas Tes Online';

    public ?int $examId = null;

    public string $statusFilter = 'all';

    public string $search = '';

    public int $pageNumber = 1;

    public static function canAccess(): bool
    {
        return OnlineExamAttemptResource::canViewAny();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['examId', 'statusFilter', 'search'], true)) {
            $this->pageNumber = 1;
        }
    }

    /** @return array<int,string> */
    public function examOptions(): array
    {
        return app(OnlineExamAccess::class)
            ->staffScope(OnlineExam::query(), auth()->user())
            ->whereHas('unit', fn (Builder $q) => $q->operational())
            ->orderBy('title')
            ->get(['id', 'unit_id', 'title'])
            ->load('unit:id,name')
            ->mapWithKeys(fn (OnlineExam $exam) => [
                $exam->id => $exam->title.' · '.$exam->unit?->name,
            ])->all();
    }

    public function refresh(): void
    {
        // wire:poll triggers a fresh authorized read on the next render.
        // No heartbeat is written to the participant's attempt.
    }

    public function nextPage(): void
    {
        $this->pageNumber = min($this->pageNumber + 1, 1000000);
    }

    public function previousPage(): void
    {
        $this->pageNumber = max(1, $this->pageNumber - 1);
    }

    protected function getViewData(): array
    {
        if (! $this->examId) {
            return ['snapshot' => null];
        }

        $exam = OnlineExam::query()->findOrFail($this->examId);

        // Revalidate authorization on every poll, filter and pagination request.
        return [
            'snapshot' => app(OnlineExamMonitoringService::class)->snapshot(
                $exam,
                auth()->user(),
                $this->pageNumber,
                $this->search,
                $this->statusFilter,
            ),
        ];
    }
}
