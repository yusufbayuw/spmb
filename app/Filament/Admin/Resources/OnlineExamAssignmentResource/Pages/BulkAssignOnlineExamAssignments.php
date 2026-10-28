<?php

namespace App\Filament\Admin\Resources\OnlineExamAssignmentResource\Pages;

use App\Filament\Admin\Resources\OnlineExamAssignmentResource;
use App\Models\OnlineExam;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Services\OnlineExamAccess;
use App\Services\OnlineExamBulkAssignmentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BulkAssignOnlineExamAssignments extends Page
{
    protected static string $resource = OnlineExamAssignmentResource::class;

    protected static string $view = 'filament.admin.resources.online-exam-assignments.bulk-assign';

    protected static ?string $title = 'Penugasan Tes Online Massal';

    public ?int $examId = null;

    public ?int $openingId = null;

    public ?int $pathwayId = null;

    /** @var array<string, mixed> */
    public array $previewData = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['examId', 'openingId', 'pathwayId'], true)) {
            $this->previewData = [];
            $this->resetErrorBag();
        }

        if ($property === 'examId') {
            $this->openingId = null;
            $this->pathwayId = null;
        }
    }

    /** @return array<int, string> */
    public function examOptions(): array
    {
        return app(OnlineExamAccess::class)
            ->staffScope(OnlineExam::query()->where('is_published', true), auth()->user())
            ->whereHas('unit', fn (Builder $q) => $q->operational())
            ->orderBy('title')
            ->get()
            ->mapWithKeys(fn (OnlineExam $exam) => [
                $exam->id => $exam->title.' · '.$exam->unit?->name,
            ])->all();
    }

    /** @return array<int, string> */
    public function openingOptions(): array
    {
        $exam = $this->selectedExam();
        if (! $exam) {
            return [];
        }

        return RegistrationOpening::query()
            ->where('unit_id', $exam->unit_id)
            ->where('status', '!=', 'archived')
            ->whereNull('archived_at')
            ->when($exam->study_program_id !== null,
                fn (Builder $q) => $q->where('study_program_id', $exam->study_program_id))
            ->with('studyProgram')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (RegistrationOpening $opening) => [
                $opening->id => trim(($opening->studyProgram?->name ?? 'Semua Prodi')
                    .' · '.$opening->academic_year.' · '.$opening->wave),
            ])->all();
    }

    /** @return array<int, string> */
    public function pathwayOptions(): array
    {
        $exam = $this->selectedExam();
        if (! $exam) {
            return [];
        }

        return RegistrationPathway::query()
            ->where('unit_id', $exam->unit_id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function preview(): void
    {
        $this->previewData = [];
        $this->resetErrorBag();

        try {
            $exam = $this->authorizedExam();
            $this->previewData = app(OnlineExamBulkAssignmentService::class)->preview(
                $exam, (int) $this->openingId, $this->pathwayId, auth()->user(),
            );
        } catch (ValidationException $exception) {
            $this->addError('selection', collect($exception->errors())->flatten()->first());
        }
    }

    public function assign(): void
    {
        $this->resetErrorBag();
        if (! isset($this->previewData['signature'])) {
            $this->addError('selection', 'Tampilkan pratinjau sebelum menugaskan peserta.');

            return;
        }

        try {
            $exam = $this->authorizedExam();
            $result = app(OnlineExamBulkAssignmentService::class)->execute(
                $exam,
                (int) $this->openingId,
                $this->pathwayId,
                (string) $this->previewData['signature'],
                auth()->user(),
            );
        } catch (ValidationException $exception) {
            $this->previewData = [];
            $this->addError('selection', collect($exception->errors())->flatten()->first());

            return;
        }

        $this->previewData = [];

        Notification::make()
            ->title('Penugasan massal selesai')
            ->body($result['created'].' penugasan baru. '
                .$result['already_assigned'].' sudah aktif. '
                .$result['revoked'].' tetap dicabut.')
            ->success()->send();
    }

    private function authorizedExam(): OnlineExam
    {
        $exam = OnlineExam::query()->findOrFail($this->examId);
        abort_unless(app(OnlineExamAccess::class)->canManage(auth()->user(), $exam), 403);

        return $exam;
    }

    private function selectedExam(): ?OnlineExam
    {
        if (! $this->examId) {
            return null;
        }

        $exam = OnlineExam::query()->find($this->examId);
        if (! $exam || ! app(OnlineExamAccess::class)->canManage(auth()->user(), $exam)) {
            return null;
        }

        return $exam;
    }
}
