<?php

namespace App\Services;

use App\Models\TrainingEnrollment;
use App\Models\TrainingLesson;
use App\Models\TrainingModule;
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
                'modules.assessment.questions',
                'modules.assessment.attempts' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->latest('id'),
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
        $lesson->loadMissing('module.program', 'module.assessment');
        $program = $lesson->module->program;
        $this->assertRole($user, $program);

        return DB::transaction(function () use ($user, $lesson, $program): TrainingEnrollment {
            $enrollment = $this->enroll($user, $program);

            if (! $this->canAccessLesson($user, $lesson, $enrollment)) {
                throw ValidationException::withMessages([
                    'training' => 'Selesaikan materi atau checkpoint sebelumnya sebelum membuka materi ini.',
                ]);
            }

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

            return $this->refreshCompletion($enrollment->fresh(), $user);
        }, 3);
    }

    public function canAccessLesson(
        User $user,
        TrainingLesson $lesson,
        ?TrainingEnrollment $enrollment = null,
    ): bool {
        $lesson->loadMissing('module.program', 'module.program.modules.lessons', 'module.program.modules.assessment');

        if (! app(TrainingGovernanceService::class)->sequenceEnforced()) {
            return true;
        }

        $program = $lesson->module->program;
        $enrollment ??= TrainingEnrollment::query()
            ->where('training_program_id', $program->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $enrollment) {
            return $this->isFirstLesson($lesson);
        }

        $modules = $program->modules->sortBy(fn (TrainingModule $module) => [$module->sort_order, $module->id])->values();
        $moduleIndex = $modules->search(fn (TrainingModule $module) => $module->id === $lesson->training_module_id);

        if ($moduleIndex === false) {
            return false;
        }

        foreach ($modules->take($moduleIndex) as $previousModule) {
            if ($previousModule->is_required && ! $this->moduleComplete($enrollment, $previousModule)) {
                return false;
            }
        }

        $lessons = $lesson->module->lessons
            ->sortBy(fn (TrainingLesson $item) => [$item->sort_order, $item->id])
            ->values();
        $lessonIndex = $lessons->search(fn (TrainingLesson $item) => $item->id === $lesson->id);

        foreach ($lessons->take($lessonIndex === false ? 0 : $lessonIndex) as $previousLesson) {
            if ($previousLesson->is_required && ! $this->lessonCompleted($enrollment, $previousLesson)) {
                return false;
            }
        }

        return true;
    }

    public function moduleUnlocked(User $user, TrainingModule $module, ?TrainingEnrollment $enrollment = null): bool
    {
        $module->loadMissing('program.modules.lessons', 'program.modules.assessment');

        if (! app(TrainingGovernanceService::class)->sequenceEnforced()) {
            return true;
        }

        $enrollment ??= TrainingEnrollment::query()
            ->where('training_program_id', $module->training_program_id)
            ->where('user_id', $user->id)
            ->first();

        $modules = $module->program->modules
            ->sortBy(fn (TrainingModule $item) => [$item->sort_order, $item->id])
            ->values();
        $index = $modules->search(fn (TrainingModule $item) => $item->id === $module->id);

        if ($index === 0) {
            return true;
        }

        if (! $enrollment || $index === false) {
            return false;
        }

        foreach ($modules->take($index) as $previousModule) {
            if ($previousModule->is_required && ! $this->moduleComplete($enrollment, $previousModule)) {
                return false;
            }
        }

        return true;
    }

    public function moduleLessonsComplete(TrainingEnrollment $enrollment, TrainingModule $module): bool
    {
        $module->loadMissing('lessons');
        $lessonIds = $module->lessons
            ->where('is_required', true)
            ->pluck('id')
            ->all();

        if ($lessonIds === []) {
            $lessonIds = $module->lessons->pluck('id')->all();
        }

        if ($lessonIds === []) {
            return true;
        }

        $completed = TrainingProgress::query()
            ->where('training_enrollment_id', $enrollment->id)
            ->where('status', 'completed')
            ->whereIn('training_lesson_id', $lessonIds)
            ->pluck('training_lesson_id')
            ->all();

        return count(array_diff($lessonIds, $completed)) === 0;
    }

    public function moduleComplete(TrainingEnrollment $enrollment, TrainingModule $module): bool
    {
        if (! $this->moduleLessonsComplete($enrollment, $module)) {
            return false;
        }

        $module->loadMissing('assessment');

        if (! app(TrainingGovernanceService::class)->masteryRequired()
            || ! $module->assessment
            || ! $module->assessment->is_active
            || ! $module->assessment->is_required) {
            return true;
        }

        return app(TrainingMasteryService::class)->passed(
            $enrollment->user_id,
            $module->assessment->id,
            $enrollment->id,
        );
    }

    public function percentage(TrainingEnrollment $enrollment): int
    {
        $enrollment->loadMissing('program.modules.lessons', 'program.modules.assessment', 'progress');

        $requiredModules = $enrollment->program->modules->where('is_required', true);
        $modules = $requiredModules->isNotEmpty() ? $requiredModules : $enrollment->program->modules;

        $total = 0;
        $done = 0;

        foreach ($modules as $module) {
            $requiredLessons = $module->lessons->where('is_required', true);
            $lessons = $requiredLessons->isNotEmpty() ? $requiredLessons : $module->lessons;

            foreach ($lessons as $lesson) {
                $total++;
                if ($this->lessonCompleted($enrollment, $lesson)) {
                    $done++;
                }
            }

            if (app(TrainingGovernanceService::class)->masteryRequired()
                && $module->assessment?->is_active
                && $module->assessment?->is_required) {
                $total++;
                if (app(TrainingMasteryService::class)->passed(
                    $enrollment->user_id,
                    $module->assessment->id,
                    $enrollment->id,
                )) {
                    $done++;
                }
            }
        }

        return $total > 0 ? (int) round(($done / $total) * 100) : 0;
    }

    public function refreshCompletion(TrainingEnrollment $enrollment, User $user): TrainingEnrollment
    {
        $enrollment->loadMissing('program.modules.lessons', 'program.modules.assessment');
        $program = $enrollment->program;

        $requiredModules = $program->modules->where('is_required', true);
        $modules = $requiredModules->isNotEmpty() ? $requiredModules : $program->modules;

        $complete = $modules->isNotEmpty()
            && $modules->every(fn (TrainingModule $module): bool => $this->moduleComplete($enrollment, $module));

        if ($complete && $enrollment->status !== 'completed') {
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
        } elseif (! $complete && $enrollment->status === 'completed') {
            $enrollment->update([
                'status' => 'in_progress',
                'completed_at' => null,
            ]);
        }

        return $enrollment->fresh(['progress']);
    }

    private function lessonCompleted(TrainingEnrollment $enrollment, TrainingLesson $lesson): bool
    {
        return TrainingProgress::query()
            ->where('training_enrollment_id', $enrollment->id)
            ->where('training_lesson_id', $lesson->id)
            ->where('status', 'completed')
            ->exists();
    }

    private function isFirstLesson(TrainingLesson $lesson): bool
    {
        $firstModule = TrainingModule::query()
            ->where('training_program_id', $lesson->module->training_program_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $firstModule || $firstModule->id !== $lesson->training_module_id) {
            return false;
        }

        return TrainingLesson::query()
            ->where('training_module_id', $lesson->training_module_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id') === $lesson->id;
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
