<?php

namespace App\Services;

use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\OnlineExamAttempt;
use App\Models\OnlineExamAttemptItem;
use App\Models\OnlineExamQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineExamEngine
{
    public function assign(OnlineExam $exam, User $participant, User $actor): OnlineExamAssignment
    {
        abort_unless(app(OnlineExamAccess::class)->canManage($actor, $exam), 403);
        if (! $participant->is_active || ! $participant->isUser()) {
            throw ValidationException::withMessages(['user_id' => 'Hanya akun pendaftar aktif yang dapat ditugaskan.']);
        }

        return OnlineExamAssignment::query()->firstOrCreate(
            ['online_exam_id' => $exam->id, 'user_id' => $participant->id],
            ['assigned_by' => $actor->id, 'is_active' => true],
        );
    }

    public function start(OnlineExamAssignment $assignment, User $actor): OnlineExamAttempt
    {
        $result = DB::transaction(function () use ($assignment, $actor): OnlineExamAttempt|ValidationException {
            $assignment = OnlineExamAssignment::query()
                ->with('exam.unit')
                ->lockForUpdate()
                ->findOrFail($assignment->id);

            abort_unless(app(OnlineExamAccess::class)->canResume($actor, $assignment), 403);
            $exam = $assignment->exam;

            $existing = $assignment->attempts()
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing && $existing->expires_at->gt(now())) {
                return $existing->load('items');
            }

            if ($existing) {
                $this->finalizeLocked($existing, true);
            }

            // A closed or unpublished package must not create new attempts.
            // Resuming an active attempt above remains possible until its own timeout.
            if (! $exam->isOpen()) {
                return ValidationException::withMessages([
                    'exam' => 'Paket tes tidak sedang dibuka untuk percobaan baru.',
                ]);
            }

            if ($assignment->attempts()->count() >= $exam->max_attempts) {
                return ValidationException::withMessages(['exam' => 'Batas jumlah pengerjaan ujian telah tercapai.']);
            }

            $items = $exam->items()->with('question')->get();

            // Fail closed instead of silently removing questions that have been
            // deactivated or moved outside the exam's owning unit.
            if ($items->isEmpty() || $items->contains(fn ($item) =>
                ! $item->question?->is_active
                || (int) $item->question->unit_id !== (int) $exam->unit_id)) {
                return ValidationException::withMessages([
                    'exam' => 'Paket ujian belum memiliki daftar soal aktif yang lengkap.',
                ]);
            }

            foreach ($items as $item) {
                try {
                    app(OnlineExamQuestionValidator::class)->validate($item->question);
                } catch (ValidationException $exception) {
                    // Commit an expired prior attempt even when a new attempt cannot start.
                    return $exception;
                }
            }

            if ($exam->shuffle_questions) {
                $items = $items->shuffle()->values();
            }

            $attempt = OnlineExamAttempt::query()->create([
                'online_exam_assignment_id' => $assignment->id,
                'online_exam_id' => $exam->id,
                'user_id' => $actor->id,
                'attempt_no' => ((int) $assignment->attempts()->max('attempt_no')) + 1,
                'status' => 'in_progress',
                'duration_minutes_snapshot' => $exam->duration_minutes,
                'passing_percentage_snapshot' => $exam->passing_percentage,
                'started_at' => now(),
                'expires_at' => now()->addMinutes($exam->duration_minutes),
            ]);

            foreach ($items as $index => $item) {
                $question = $item->question;
                $choices = $question->options['choices'] ?? [];
                if ($question->type === 'true_false') {
                    $choices = ['1' => 'Benar', '0' => 'Salah'];
                }
                if ($exam->shuffle_options && in_array($question->type, [
                    'single_choice', 'multiple_choice', 'matching',
                ], true)) {
                    $keys = array_keys($choices);
                    shuffle($keys);
                    $choices = collect($keys)->mapWithKeys(
                        fn ($key) => [(string) $key => $choices[$key]]
                    )->all();
                }

                OnlineExamAttemptItem::query()->create([
                    'online_exam_attempt_id' => $attempt->id,
                    'online_exam_question_id' => $question->id,
                    'type' => $question->type,
                    'display_snapshot' => [
                        'prompt' => $question->prompt,
                        'choices' => $choices,
                        'prompts' => $question->options['prompts'] ?? [],
                    ],
                    'grading_snapshot' => $question->grading_config ?? [],
                    'points' => $question->points,
                    'sort_order' => $index + 1,
                ]);
            }

            app(AuditTrail::class)->record(
                'online_exam.started',
                $attempt,
                actor: $actor,
                unitId: $exam->unit_id,
                description: 'Peserta memulai Tes Online mandiri.',
            );

            return $attempt->load('items');
        }, 5);

        // Throw outside the transaction: an expired attempt must stay finalized
        // even if the next attempt is rejected by a business validation rule.
        if ($result instanceof ValidationException) {
            throw $result;
        }

        return $result;
    }

    public function save(OnlineExamAttempt $attempt, User $actor, int $itemId, mixed $answer): void
    {
        DB::transaction(function () use ($attempt, $actor, $itemId, $answer): void {
            $attempt = OnlineExamAttempt::query()->with('assignment.exam.unit')
                ->lockForUpdate()->findOrFail($attempt->id);
            $this->assertOwner($attempt, $actor);

            if ($attempt->status !== 'in_progress') {
                throw ValidationException::withMessages(['answer' => 'Ujian telah selesai; jawaban tidak dapat diubah.']);
            }

            if ($attempt->expires_at->lte(now())) {
                // Commit finalization rather than throwing inside this DB
                // transaction (which would roll back the timeout result).
                $this->finalizeLocked($attempt, true);

                return;
            }

            $item = OnlineExamAttemptItem::query()
                ->where('online_exam_attempt_id', $attempt->id)
                ->whereKey($itemId)->lockForUpdate()->firstOrFail();

            $value = $this->normalizeAnswer($item, $answer);
            $item->update(['answer' => ['value' => $value], 'answered_at' => now()]);
        }, 5);
    }

    public function submit(OnlineExamAttempt $attempt, User $actor): OnlineExamAttempt
    {
        return DB::transaction(function () use ($attempt, $actor): OnlineExamAttempt {
            $attempt = OnlineExamAttempt::query()->with('assignment.exam.unit')
                ->lockForUpdate()->findOrFail($attempt->id);
            $this->assertOwner($attempt, $actor);

            if ($attempt->status === 'in_progress') {
                $this->finalizeLocked($attempt, $attempt->expires_at->lte(now()));
            }

            return $attempt->fresh(['items', 'exam']);
        }, 5);
    }

    public function refreshTimeout(OnlineExamAttempt $attempt, User $actor): OnlineExamAttempt
    {
        if ($attempt->status === 'in_progress' && $attempt->expires_at->lte(now())) {
            return $this->submit($attempt, $actor);
        }

        $attempt->loadMissing('assignment.exam.unit');
        $this->assertOwner($attempt, $actor);

        return $attempt;
    }

    public function reviewEssay(OnlineExamAttempt $attempt, User $actor, array $scores): OnlineExamAttempt
    {
        return DB::transaction(function () use ($attempt, $actor, $scores): OnlineExamAttempt {
            $attempt = OnlineExamAttempt::query()->with('exam.unit')
                ->lockForUpdate()->findOrFail($attempt->id);
            abort_unless(app(OnlineExamAccess::class)->canManage($actor, $attempt->exam), 403);

            if ($attempt->status !== 'pending_review') {
                throw ValidationException::withMessages(['review' => 'Ujian tidak membutuhkan koreksi esai.']);
            }

            $items = $attempt->items()->get();
            foreach ($items as $item) {
                if ($item->type !== 'essay' || $item->earned_points !== null) {
                    continue;
                }

                $value = $scores[$item->id] ?? null;
                if (! is_numeric($value) || ! is_finite((float) $value)
                    || (float) $value < 0 || (float) $value > (float) $item->points) {
                    throw ValidationException::withMessages([
                        'review' => 'Nilai esai wajib berupa angka antara 0 dan bobot soal.',
                    ]);
                }

                $item->update([
                    'earned_points' => round((float) $value, 2),
                    'graded_at' => now(),
                    'reviewed_by' => $actor->id,
                ]);
            }

            $this->summarize($attempt, $items->fresh(), false);
            $attempt->update(['reviewed_at' => now()]);

            app(AuditTrail::class)->record(
                'online_exam.reviewed',
                $attempt,
                actor: $actor,
                unitId: $attempt->exam->unit_id,
                description: 'Penilaian esai Tes Online telah disahkan.',
            );

            return $attempt->fresh(['items']);
        }, 5);
    }

    /**
     * Finalize an abandoned attempt without requiring the participant to return.
     * Used by the scheduler; the DB lock and status guard make retries safe.
     */
    public function finalizeExpired(OnlineExamAttempt $attempt): bool
    {
        return DB::transaction(function () use ($attempt): bool {
            $locked = OnlineExamAttempt::query()
                ->with(['exam', 'user'])
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            if ($locked->status !== 'in_progress' || $locked->expires_at->gt(now())) {
                return false;
            }

            $this->finalizeLocked($locked, true);

            return true;
        }, 5);
    }

    private function assertOwner(OnlineExamAttempt $attempt, User $actor): void
    {
        abort_unless(
            $actor->is_active && $actor->isUser()
            && (int) $attempt->user_id === (int) $actor->id
            && $attempt->assignment?->is_active
            && $attempt->assignment?->exam?->unit?->isOperational(),
            403,
        );
    }

    private function normalizeAnswer(OnlineExamAttemptItem $item, mixed $value): mixed
    {
        $snapshot = $item->display_snapshot;
        $choices = $snapshot['choices'] ?? [];
        $type = $item->type;

        if (in_array($type, ['single_choice', 'true_false'], true)) {
            if (! is_scalar($value) || ! array_key_exists((string) $value, $choices)) {
                throw ValidationException::withMessages(['answer' => 'Pilihan jawaban tidak valid.']);
            }

            return (string) $value;
        }

        if ($type === 'multiple_choice') {
            if (! is_array($value) || count($value) > count($choices)
                || count($value) !== count(array_unique(array_map('strval', $value)))
                || ! collect($value)->every(fn ($v) => is_scalar($v) && array_key_exists((string) $v, $choices))) {
                throw ValidationException::withMessages(['answer' => 'Pilihan jawaban tidak valid.']);
            }

            return array_values(array_map('strval', $value));
        }

        if ($type === 'matching') {
            $prompts = $snapshot['prompts'] ?? [];
            if (! is_array($value) || count($value) > count($prompts)
                || ! collect($value)->every(fn ($v, $k) =>
                    array_key_exists((string) $k, $prompts)
                    && ($v === '' || $v === null
                        || (is_scalar($v) && array_key_exists((string) $v, $choices))))) {
                throw ValidationException::withMessages(['answer' => 'Pasangan jawaban tidak valid.']);
            }

            return collect($value)
                ->reject(fn ($v) => $v === '' || $v === null)
                ->mapWithKeys(fn ($v, $k) => [(string) $k => (string) $v])->all();
        }

        if (in_array($type, ['short_answer', 'essay'], true)) {
            if (! is_string($value) || mb_strlen($value) > ($type === 'essay' ? 15000 : 500)) {
                throw ValidationException::withMessages(['answer' => 'Panjang jawaban melebihi batas.']);
            }

            return trim($value);
        }

        if ($type === 'numerical') {
            // A cleared number input must erase a previously saved answer.
            if ($value === '' || $value === null) {
                return null;
            }

            if (! is_scalar($value) || ! is_numeric($value)
                || ! is_finite((float) $value)) {
                throw ValidationException::withMessages(['answer' => 'Masukkan angka yang valid.']);
            }

            return (float) $value;
        }

        throw ValidationException::withMessages(['answer' => 'Jenis soal tidak dikenali.']);
    }

    private function finalizeLocked(OnlineExamAttempt $attempt, bool $timedOut): void
    {
        if ($attempt->status !== 'in_progress') {
            return;
        }

        $items = $attempt->items()->get();
        foreach ($items as $item) {
            $value = $item->answer['value'] ?? null;
            if ($item->type === 'essay' && filled($value)) {
                continue;
            }

            $item->update([
                'earned_points' => $this->scoreItem($item, $value),
                'graded_at' => now(),
            ]);
        }

        $this->summarize($attempt, $items->fresh(), $timedOut);

        app(AuditTrail::class)->record(
            'online_exam.submitted',
            $attempt,
            actor: $attempt->user,
            unitId: $attempt->exam->unit_id,
            metadata: ['timed_out' => $timedOut],
            description: 'Tes Online telah dikumpulkan tanpa perubahan tahap pendaftaran.',
        );
    }

    private function scoreItem(OnlineExamAttemptItem $item, mixed $value): float
    {
        if ($value === null || $value === '' || $value === []) {
            return 0;
        }

        $key = $item->grading_snapshot ?? [];
        $points = (float) $item->points;
        $correct = false;

        if (in_array($item->type, ['single_choice', 'true_false'], true)) {
            $correct = (string) $value === (string) ($key['correct'] ?? '');
        } elseif ($item->type === 'multiple_choice') {
            $actual = array_values(array_map('strval', is_array($value) ? $value : []));
            $expected = array_values(array_map('strval', $key['correct_options'] ?? []));
            sort($actual);
            sort($expected);
            $correct = $actual === $expected;
        } elseif ($item->type === 'matching') {
            $pairs = $key['pairs'] ?? [];
            $matched = 0;
            foreach ($pairs as $promptId => $choiceId) {
                $matched += (string) ($value[$promptId] ?? '') === (string) $choiceId ? 1 : 0;
            }

            return count($pairs) > 0 ? round($points * $matched / count($pairs), 2) : 0;
        } elseif ($item->type === 'short_answer') {
            $actual = trim((string) $value);
            if (! ($key['case_sensitive'] ?? false)) {
                $actual = mb_strtolower($actual);
            }
            foreach (($key['accepted'] ?? []) as $answer) {
                $accepted = trim((string) $answer);
                if (! ($key['case_sensitive'] ?? false)) {
                    $accepted = mb_strtolower($accepted);
                }
                if ($accepted === $actual) {
                    $correct = true;
                    break;
                }
            }
        } elseif ($item->type === 'numerical') {
            $correct = is_numeric($value)
                && abs((float) $value - (float) ($key['value'] ?? 0))
                    <= (float) ($key['tolerance'] ?? 0) + 0.000000001;
        }

        return $correct ? $points : 0;
    }

    private function summarize(OnlineExamAttempt $attempt, $items, bool $timedOut): void
    {
        $pending = $items->contains(fn ($item) => $item->earned_points === null);
        $maximum = round($items->sum(fn ($item) => (float) $item->points), 2);
        $earned = round($items->sum(fn ($item) => (float) ($item->earned_points ?? 0)), 2);
        $percentage = $maximum > 0 ? round(100 * $earned / $maximum, 2) : 0;
        $attempt->update([
            'status' => $pending ? 'pending_review' : 'graded',
            'earned_points' => $earned,
            'maximum_points' => $maximum,
            'percentage' => $pending ? null : $percentage,
            'passed' => $pending ? null : ($percentage >= (float) $attempt->passing_percentage_snapshot),
            'submitted_at' => $attempt->submitted_at ?? now(),
        ]);
    }
}
