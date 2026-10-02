<?php

namespace App\Filament\Admin\Pages;

use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingModule;
use App\Models\TrainingModuleAssessment;
use App\Models\TrainingModuleAttempt;
use App\Models\TrainingProgram;
use App\Services\CertificationAccessService;
use App\Services\TrainingMasteryService;
use App\Services\TrainingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class TrainingCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Training Saya';
    protected static ?string $navigationGroup = 'Training & Sertifikasi';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Pusat Training SPMB';
    protected static string $view = 'filament.admin.pages.training-center';

    public ?string $assessmentAttemptUuid = null;
    public array $assessmentAnswers = [];
    public array $lastAssessmentResult = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->is_active
            && $user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])
            && $user->hasOperationalUnitAccess();
    }

    public function mount(): void
    {
        $this->assessmentAttemptUuid = TrainingModuleAttempt::query()
            ->where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->latest('id')
            ->value('uuid');
    }

    public function programs(): Collection
    {
        return app(TrainingService::class)->availablePrograms(auth()->user());
    }

    public function governanceWarning(): ?string
    {
        return app(CertificationAccessService::class)->warning(auth()->user());
    }

    public function startTraining(string $programUuid): void
    {
        $program = TrainingProgram::query()->where('uuid', $programUuid)->firstOrFail();
        app(TrainingService::class)->enroll(auth()->user(), $program);

        Notification::make()
            ->title('Training dimulai')
            ->body('Ikuti lesson dan checkpoint secara berurutan. Progress tersimpan otomatis.')
            ->success()
            ->send();
    }

    public function completeLesson(string $lessonUuid): void
    {
        $lesson = TrainingLesson::query()
            ->where('uuid', $lessonUuid)
            ->with('module.program')
            ->firstOrFail();

        $enrollment = app(TrainingService::class)->completeLesson(auth()->user(), $lesson);

        Notification::make()
            ->title($enrollment->status === 'completed' ? 'Training selesai' : 'Materi diselesaikan')
            ->body($enrollment->status === 'completed'
                ? 'Seluruh requirement training dan checkpoint telah terpenuhi.'
                : 'Progress tersimpan. Lanjutkan materi atau checkpoint berikutnya.')
            ->success()
            ->send();
    }

    public function progressFor(TrainingProgram $program): int
    {
        $enrollment = $program->enrollments->first();

        return $enrollment
            ? app(TrainingService::class)->percentage($enrollment)
            : 0;
    }

    public function moduleUnlocked(TrainingModule $module, ?TrainingEnrollment $enrollment): bool
    {
        return app(TrainingService::class)->moduleUnlocked(auth()->user(), $module, $enrollment);
    }

    public function lessonUnlocked(TrainingLesson $lesson, ?TrainingEnrollment $enrollment): bool
    {
        return $enrollment
            && app(TrainingService::class)->canAccessLesson(auth()->user(), $lesson, $enrollment);
    }

    public function assessmentPassed(TrainingModuleAssessment $assessment, ?TrainingEnrollment $enrollment): bool
    {
        return $enrollment
            && app(TrainingMasteryService::class)->passed(
                auth()->id(),
                $assessment->id,
                $enrollment->id,
            );
    }

    public function assessmentReady(TrainingModuleAssessment $assessment, ?TrainingEnrollment $enrollment): bool
    {
        return $enrollment
            && app(TrainingMasteryService::class)->canStart(auth()->user(), $assessment);
    }

    public function startAssessment(string $assessmentUuid): void
    {
        $assessment = TrainingModuleAssessment::query()
            ->where('uuid', $assessmentUuid)
            ->with(['module.program', 'module.lessons', 'questions'])
            ->firstOrFail();

        $attempt = app(TrainingMasteryService::class)->start(auth()->user(), $assessment);

        $this->assessmentAttemptUuid = $attempt->uuid;
        $this->assessmentAnswers = [];
        $this->lastAssessmentResult = [];
    }

    public function activeAssessmentAttempt(): ?TrainingModuleAttempt
    {
        if (! $this->assessmentAttemptUuid) {
            return null;
        }

        return TrainingModuleAttempt::query()
            ->where('uuid', $this->assessmentAttemptUuid)
            ->where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->with(['attemptQuestions', 'assessment.module.program'])
            ->first();
    }

    public function submitAssessment(): void
    {
        $attempt = TrainingModuleAttempt::query()
            ->where('uuid', $this->assessmentAttemptUuid)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $result = app(TrainingMasteryService::class)->submit(
            $attempt,
            auth()->user(),
            $this->assessmentAnswers,
        );

        $this->lastAssessmentResult = [
            'status' => $result->status,
            'score' => (float) $result->score,
            'passing_score' => $result->passing_score_snapshot,
            'module' => $result->assessment->module->title,
        ];
        $this->assessmentAttemptUuid = null;
        $this->assessmentAnswers = [];

        Notification::make()
            ->title($result->status === 'passed' ? 'Checkpoint lulus' : 'Checkpoint belum lulus')
            ->body('Nilai: '.number_format((float) $result->score, 2, ',', '.'))
            ->color($result->status === 'passed' ? 'success' : 'danger')
            ->send();
    }
}
