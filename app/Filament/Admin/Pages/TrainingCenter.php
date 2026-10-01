<?php

namespace App\Filament\Admin\Pages;

use App\Models\TrainingLesson;
use App\Models\TrainingProgram;
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

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->is_active
            && $user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])
            && $user->hasOperationalUnitAccess();
    }

    public function programs(): Collection
    {
        return app(TrainingService::class)->availablePrograms(auth()->user());
    }

    public function startTraining(string $programUuid): void
    {
        $program = TrainingProgram::query()->where('uuid', $programUuid)->firstOrFail();
        app(TrainingService::class)->enroll(auth()->user(), $program);

        Notification::make()
            ->title('Training dimulai')
            ->body('Progress Anda akan tersimpan otomatis per materi.')
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
                ? 'Anda sekarang memenuhi syarat training untuk sertifikasi yang terkait.'
                : 'Progress materi berhasil disimpan.')
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
}
