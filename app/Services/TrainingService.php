<?php

namespace App\Services;

use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingProgram;
use App\Models\TrainingProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingService
{
    public function availablePrograms(User $user): Collection
    {
        return TrainingProgram::query()
            ->active()
            ->forUser($user)
            ->with([
                'modules.lessons',
                'enrollments' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->with('progress'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function enroll(User $user, TrainingProgram $program): TrainingEnrollment
    {
        $this->assertRole($user, $program);

        $enrollment = TrainingEnrollment::query()->firstOrCreate(
            [
                'training_program_id' => $program->id,
                'user_id' => $user->id,
            ],
            [
                'status' => 'in_progress',
                'enrolled_at' => now(),
                'started_at' => now(),
            ],
        );

        if ($enrollment->status === 'enrolled') {
            $enrollment->update([
                'status' => 'in_progress',
                'started_at' => $enrollment->started_at ?: now(),
            ]);
        }

        return $enrollment->fresh(['progress']);
    }

    public function completeLesson(User $user, TrainingLesson $lesson): TrainingEnrollment
    {
        $lesson->loadMissing('module.program');
        $program = $lesson->module->program;
        $this->assertRole($user, $program);

        return DB::transaction(function () use ($user, $lesson, $program): TrainingEnrollment {
            $enrollment = $this->enroll($user, $program);

            TrainingProgress::query()->updateOrCreate(
                [
                    'training_enrollment_id' => $enrollment->id,
                    'training_lesson_id' => $lesson->id,
                ],
                [
                    'status' => 'completed',
                    'progress_percent' => 100,
                    'started_at' => now(),
                    'completed_at' => now(),
                ],
            );

            $lessonIds = $this->completionLessonIds($program);
            $completedIds = TrainingProgress::query()
                ->where('training_enrollment_id', $enrollment->id)
                ->where('status', 'completed')
                ->whereIn('training_lesson_id', $lessonIds)
                ->pluck('training_lesson_id')
                ->all();

            if ($lessonIds !== [] && count(array_diff($lessonIds, $completedIds)) === 0) {
                $enrollment->update([
                    'status' => 'completed',
                    'completed_at' => $enrollment->completed_at ?: now(),
                ]);

                app(AuditTrail::class)->record(
                    'training.completed',
                    $enrollment,
                    actor: $user,
                    description: 'Pelatihan '.$program->name.' diselesaikan',
                );
            }

            return $enrollment->fresh(['progress']);
        }, 3);
    }

    public function percentage(TrainingEnrollment $enrollment): int
    {
        $enrollment->loadMissing('program.modules.lessons', 'progress');

        $lessonIds = $enrollment->program->modules
            ->flatMap(fn ($module) => $module->lessons)
            ->pluck('id')
            ->all();

        if ($lessonIds === []) {
            return 0;
        }

        $completed = $enrollment->progress
            ->where('status', 'completed')
            ->whereIn('training_lesson_id', $lessonIds)
            ->count();

        return (int) round(($completed / count($lessonIds)) * 100);
    }

    private function completionLessonIds(TrainingProgram $program): array
    {
        $program->loadMissing('modules.lessons');

        $allLessons = $program->modules->flatMap(fn ($module) => $module->lessons);
        $required = $allLessons->where('is_required', true)->pluck('id')->all();

        return $required !== [] ? $required : $allLessons->pluck('id')->all();
    }

    private function assertRole(User $user, TrainingProgram $program): void
    {
        if (! $user->is_active || ! $user->hasRole($program->target_role)) {
            throw ValidationException::withMessages([
                'training' => 'Program pelatihan tidak tersedia untuk role pengguna ini.',
            ]);
        }
    }
}
