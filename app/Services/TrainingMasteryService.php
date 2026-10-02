<?php

namespace App\Services;

use App\Models\TrainingModuleAnswer;
use App\Models\TrainingModuleAssessment;
use App\Models\TrainingModuleAttempt;
use App\Models\TrainingModuleAttemptQuestion;
use App\Models\TrainingEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingMasteryService
{
    public function passed(int $userId, int $assessmentId, ?int $enrollmentId = null): bool
    {
        return TrainingModuleAttempt::query()
            ->where('training_module_assessment_id', $assessmentId)
            ->where('user_id', $userId)
            ->when($enrollmentId, fn ($query) => $query->where('training_enrollment_id', $enrollmentId))
            ->where('status', 'passed')
            ->exists();
    }

    public function canStart(User $user, TrainingModuleAssessment $assessment): bool
    {
        $assessment->loadMissing('module.program', 'module.lessons');

        $enrollment = TrainingEnrollment::query()
            ->where('training_program_id', $assessment->module->training_program_id)
            ->where('user_id', $user->id)
            ->first();

        if (! $enrollment
            || ! $assessment->is_active
            || ! $user->is_active
            || ! $user->hasRole($assessment->module->program->target_role)) {
            return false;
        }

        return app(TrainingService::class)->moduleUnlocked($user, $assessment->module, $enrollment)
            && app(TrainingService::class)->moduleLessonsComplete($enrollment, $assessment->module);
    }

    public function start(User $user, TrainingModuleAssessment $assessment): TrainingModuleAttempt
    {
        $assessment->loadMissing('module.program', 'module.lessons', 'questions');

        if (! $this->canStart($user, $assessment)) {
            throw ValidationException::withMessages([
                'training' => 'Checkpoint modul belum dapat dimulai. Selesaikan seluruh materi wajib pada modul ini terlebih dahulu.',
            ]);
        }

        $enrollment = TrainingEnrollment::query()
            ->where('training_program_id', $assessment->module->training_program_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($this->passed($user->id, $assessment->id, $enrollment->id)) {
            throw ValidationException::withMessages([
                'training' => 'Checkpoint modul ini sudah lulus.',
            ]);
        }

        $existing = TrainingModuleAttempt::query()
            ->where('training_module_assessment_id', $assessment->id)
            ->where('training_enrollment_id', $enrollment->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing->load('attemptQuestions');
        }

        $attemptCount = TrainingModuleAttempt::query()
            ->where('training_module_assessment_id', $assessment->id)
            ->where('training_enrollment_id', $enrollment->id)
            ->where('user_id', $user->id)
            ->count();

        if ($assessment->max_attempts && $attemptCount >= $assessment->max_attempts) {
            throw ValidationException::withMessages([
                'training' => 'Batas percobaan checkpoint modul telah tercapai.',
            ]);
        }

        $questions = $assessment->questions
            ->where('is_active', true)
            ->values();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'training' => 'Checkpoint modul belum memiliki soal aktif.',
            ]);
        }

        $questionCount = $assessment->question_count
            ? min($assessment->question_count, $questions->count())
            : $questions->count();

        $selected = $questions->shuffle()->take($questionCount)->values();

        return DB::transaction(function () use (
            $user,
            $assessment,
            $enrollment,
            $selected,
            $questionCount,
            $attemptCount,
        ): TrainingModuleAttempt {
            $attempt = TrainingModuleAttempt::query()->create([
                'training_module_assessment_id' => $assessment->id,
                'training_enrollment_id' => $enrollment->id,
                'user_id' => $user->id,
                'attempt_no' => $attemptCount + 1,
                'status' => 'in_progress',
                'passing_score_snapshot' => $assessment->passing_score,
                'question_count_snapshot' => $questionCount,
                'started_at' => now(),
            ]);

            foreach ($selected as $index => $question) {
                TrainingModuleAttemptQuestion::query()->create([
                    'training_module_attempt_id' => $attempt->id,
                    'training_module_question_id' => $question->id,
                    'type' => $question->type,
                    'question' => $question->question,
                    'options' => $question->options,
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                    'weight' => $question->weight,
                    'sort_order' => $index + 1,
                ]);
            }

            app(AuditTrail::class)->record(
                'training.module_assessment_started',
                $attempt,
                actor: $user,
                metadata: [
                    'assessment_id' => $assessment->id,
                    'attempt_no' => $attempt->attempt_no,
                    'question_count' => $questionCount,
                ],
                description: 'Checkpoint modul '.$assessment->module->title.' dimulai',
            );

            return $attempt->fresh('attemptQuestions');
        }, 3);
    }

    public function submit(TrainingModuleAttempt $attempt, User $user, array $answers): TrainingModuleAttempt
    {
        $result = DB::transaction(function () use ($attempt, $user, $answers): TrainingModuleAttempt {
            $locked = TrainingModuleAttempt::query()
                ->whereKey($attempt->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->with(['attemptQuestions', 'assessment.module.program', 'enrollment'])
                ->firstOrFail();

            if ($locked->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'training' => 'Checkpoint ini sudah diselesaikan.',
                ]);
            }

            $totalWeight = 0.0;
            $earned = 0.0;

            foreach ($locked->attemptQuestions as $question) {
                $weight = max(0.01, (float) $question->weight);
                $answer = array_key_exists($question->id, $answers)
                    ? trim((string) $answers[$question->id])
                    : null;
                $correct = $answer !== null
                    && hash_equals((string) $question->correct_answer, $answer);
                $score = $correct ? $weight : 0.0;

                $totalWeight += $weight;
                $earned += $score;

                TrainingModuleAnswer::query()->updateOrCreate(
                    [
                        'training_module_attempt_id' => $locked->id,
                        'training_module_attempt_question_id' => $question->id,
                    ],
                    [
                        'training_module_question_id' => $question->training_module_question_id,
                        'answer' => $answer,
                        'is_correct' => $correct,
                        'score' => $score,
                    ],
                );
            }

            $score = $totalWeight > 0
                ? round(($earned / $totalWeight) * 100, 2)
                : 0.0;
            $passing = $locked->passing_score_snapshot
                ?? $locked->assessment->passing_score;
            $passed = $score >= $passing;

            $locked->update([
                'score' => $score,
                'status' => $passed ? 'passed' : 'failed',
                'submitted_at' => now(),
            ]);

            app(AuditTrail::class)->record(
                'training.module_assessment_submitted',
                $locked,
                actor: $user,
                metadata: [
                    'score' => $score,
                    'passing_score' => $passing,
                    'passed' => $passed,
                ],
                description: 'Checkpoint modul '.$locked->assessment->module->title.' diselesaikan',
            );

            return $locked->fresh(['attemptQuestions', 'assessment.module.program', 'enrollment']);
        }, 3);

        if ($result->status === 'passed') {
            app(TrainingService::class)->refreshCompletion(
                $result->enrollment,
                $user,
            );
        }

        return $result;
    }
}
