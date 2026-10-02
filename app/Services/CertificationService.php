<?php

namespace App\Services;

use App\Models\CertificationAnswer;
use App\Models\CertificationAttempt;
use App\Models\CertificationAttemptQuestion;
use App\Models\CertificationProgram;
use App\Models\PracticalRun;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Models\UserCertification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificationService
{
    public function availablePrograms(User $user): Collection
    {
        return CertificationProgram::query()
            ->active()
            ->forUser($user)
            ->with([
                'trainingProgram',
                'questions' => fn ($query) => $query->where('is_active', true),
                'practicalScenarios' => fn ($query) => $query->where('is_active', true),
                'attempts' => fn ($query) => $query->where('user_id', $user->id)->latest('id'),
                'certifications' => fn ($query) => $query->where('user_id', $user->id)->latest('issued_at'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function eligible(User $user, CertificationProgram $program): bool
    {
        if (! $user->is_active || ! $user->hasRole($program->target_role) || ! $program->is_active) {
            return false;
        }

        if (! $program->training_program_id) {
            return true;
        }

        return TrainingEnrollment::query()
            ->where('training_program_id', $program->training_program_id)
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->exists();
    }

    public function latestPassedTheory(User $user, CertificationProgram $program): ?CertificationAttempt
    {
        return CertificationAttempt::query()
            ->where('certification_program_id', $program->id)
            ->where('user_id', $user->id)
            ->where('status', 'passed')
            ->whereDoesntHave('certification')
            ->latest('submitted_at')
            ->latest('id')
            ->first();
    }

    public function practicalComplete(User $user, CertificationProgram $program, ?CertificationAttempt $attempt = null): bool
    {
        $attempt ??= $this->latestPassedTheory($user, $program);

        if (! $attempt) {
            return false;
        }

        $scenarioIds = $attempt->hasPracticalRequirementsSnapshot()
            ? collect($attempt->requiredPracticalScenarioIds())
            : $program->practicalScenarios()
                ->where('is_active', true)
                ->pluck('id');

        if ($scenarioIds->isEmpty()) {
            return true;
        }

        $passedScenarioIds = PracticalRun::query()
            ->where('certification_attempt_id', $attempt->id)
            ->where('user_id', $user->id)
            ->where('status', 'passed')
            ->where('passed', true)
            ->whereIn('practical_scenario_id', $scenarioIds)
            ->distinct()
            ->pluck('practical_scenario_id');

        return $scenarioIds->diff($passedScenarioIds)->isEmpty();
    }

    public function start(User $user, CertificationProgram $program): CertificationAttempt
    {
        if (! $this->eligible($user, $program)) {
            throw ValidationException::withMessages([
                'certification' => 'Syarat training untuk ujian sertifikasi belum terpenuhi.',
            ]);
        }

        if (UserCertification::query()
            ->where('user_id', $user->id)
            ->where('certification_program_id', $program->id)
            ->valid()
            ->exists()) {
            throw ValidationException::withMessages([
                'certification' => 'Sertifikasi ini masih aktif dan belum memerlukan ujian ulang.',
            ]);
        }

        if ($this->latestPassedTheory($user, $program)) {
            throw ValidationException::withMessages([
                'certification' => 'Ujian teori sudah lulus. Selesaikan practical exam yang masih diperlukan.',
            ]);
        }

        $activeQuestions = $program->questions()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($activeQuestions->isEmpty()) {
            throw ValidationException::withMessages([
                'certification' => 'Program sertifikasi belum memiliki soal aktif.',
            ]);
        }

        $existing = CertificationAttempt::query()
            ->where('certification_program_id', $program->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if ($existing && ! $existing->isExpired()) {
            return $existing->load('attemptQuestions');
        }

        if ($existing && $existing->isExpired()) {
            $this->expireAttempt($existing, $user);
        }

        $this->assertAttemptPolicy($user, $program);

        $questionCount = $program->question_count
            ? min($program->question_count, $activeQuestions->count())
            : $activeQuestions->count();

        $selectedQuestions = $program->shuffle_questions
            ? $activeQuestions->shuffle()->take($questionCount)->values()
            : $activeQuestions->take($questionCount)->values();

        $attemptNo = ((int) CertificationAttempt::query()
            ->where('certification_program_id', $program->id)
            ->where('user_id', $user->id)
            ->max('attempt_no')) + 1;

        return DB::transaction(function () use (
            $user,
            $program,
            $selectedQuestions,
            $questionCount,
            $attemptNo,
        ): CertificationAttempt {
            $startedAt = now();
            $timeLimit = $program->time_limit_minutes;

            $attempt = CertificationAttempt::query()->create([
                'certification_program_id' => $program->id,
                'user_id' => $user->id,
                'program_code_snapshot' => $program->code,
                'program_name_snapshot' => $program->name,
                'program_version_snapshot' => $program->version,
                'passing_score_snapshot' => $program->passing_score,
                'theory_weight_snapshot' => $program->theory_weight,
                'practical_weight_snapshot' => $program->practical_weight,
                'practical_passing_score_snapshot' => $program->practical_passing_score,
                'valid_months_snapshot' => $program->valid_months,
                'question_count_snapshot' => $questionCount,
                'time_limit_minutes_snapshot' => $timeLimit,
                'required_practical_scenario_ids_snapshot' => $program->practicalScenarios()
                    ->where('is_active', true)
                    ->pluck('id')
                    ->values()
                    ->all(),
                'attempt_no' => $attemptNo,
                'status' => 'in_progress',
                'started_at' => $startedAt,
                'expires_at' => $timeLimit
                    ? $startedAt->copy()->addMinutes($timeLimit)
                    : null,
            ]);

            foreach ($selectedQuestions as $index => $question) {
                CertificationAttemptQuestion::query()->create([
                    'certification_attempt_id' => $attempt->id,
                    'certification_question_id' => $question->id,
                    'type' => $question->type,
                    'question' => $question->question,
                    'options' => $this->snapshotOptions(
                        $question->options,
                        $question->type,
                        (bool) $program->shuffle_options,
                    ),
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                    'weight' => $question->weight,
                    'sort_order' => $index + 1,
                ]);
            }

            app(AuditTrail::class)->record(
                'certification.exam_started',
                $attempt,
                actor: $user,
                metadata: [
                    'program_code' => $program->code,
                    'program_version' => $program->version,
                    'attempt_no' => $attemptNo,
                    'question_count' => $questionCount,
                    'expires_at' => $attempt->expires_at?->toIso8601String(),
                ],
                description: 'Ujian teori '.$program->name.' dimulai',
            );

            return $attempt->fresh(['attemptQuestions']);
        }, 3);
    }

    public function submit(CertificationAttempt $attempt, User $user, array $answers): CertificationAttempt
    {
        $evaluated = DB::transaction(function () use ($attempt, $user, $answers): CertificationAttempt {
            $locked = CertificationAttempt::query()
                ->whereKey($attempt->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->with(['attemptQuestions', 'program'])
                ->firstOrFail();

            if ($locked->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'certification' => 'Ujian ini sudah diselesaikan.',
                ]);
            }

            if ($locked->isExpired()) {
                $locked->update([
                    'score' => 0,
                    'status' => 'failed',
                    'submitted_at' => now(),
                ]);

                app(AuditTrail::class)->record(
                    'certification.exam_expired',
                    $locked,
                    actor: $user,
                    metadata: ['score' => 0],
                    description: 'Ujian teori berakhir karena batas waktu terlewati',
                );

                return $locked->fresh(['program', 'attemptQuestions', 'answers', 'certification']);
            }

            if (! $this->eligible($user, $locked->program)) {
                throw ValidationException::withMessages([
                    'certification' => 'Pengguna tidak lagi memenuhi syarat ujian ini.',
                ]);
            }

            if ($locked->attemptQuestions->isEmpty()) {
                $this->materializeLegacyAttemptQuestions($locked);
                $locked->load('attemptQuestions');
            }

            $totalWeight = 0.0;
            $earned = 0.0;

            foreach ($locked->attemptQuestions as $question) {
                $weight = max(0.01, (float) $question->weight);
                $answer = array_key_exists($question->id, $answers)
                    ? trim((string) $answers[$question->id])
                    : null;

                $isCorrect = $answer !== null
                    && hash_equals((string) $question->correct_answer, $answer);

                $answerScore = $isCorrect ? $weight : 0.0;
                $totalWeight += $weight;
                $earned += $answerScore;

                CertificationAnswer::query()->updateOrCreate(
                    [
                        'certification_attempt_id' => $locked->id,
                        'certification_attempt_question_id' => $question->id,
                    ],
                    [
                        'certification_question_id' => $question->certification_question_id,
                        'answer' => $answer,
                        'is_correct' => $isCorrect,
                        'score' => $answerScore,
                    ],
                );
            }

            $score = round(($earned / $totalWeight) * 100, 2);
            $passingScore = $locked->passing_score_snapshot
                ?? $locked->program->passing_score;

            $passed = $score >= $passingScore;

            $locked->update([
                'score' => $score,
                'status' => $passed ? 'passed' : 'failed',
                'submitted_at' => now(),
            ]);

            app(AuditTrail::class)->record(
                'certification.exam_submitted',
                $locked,
                actor: $user,
                metadata: [
                    'score' => $score,
                    'passing_score' => $passingScore,
                    'passed' => $passed,
                ],
                description: 'Ujian '.$locked->program_name_snapshot.' diselesaikan',
            );

            return $locked->fresh([
                'program',
                'attemptQuestions',
                'answers',
                'certification',
            ]);
        }, 3);

        if ($evaluated->status === 'passed') {
            $this->issueIfComplete($user, $evaluated->program, $evaluated);
        }

        return $evaluated->fresh([
            'program',
            'attemptQuestions',
            'answers',
            'certification',
        ]);
    }

    public function issueIfComplete(
        User $user,
        CertificationProgram $program,
        ?CertificationAttempt $attempt = null,
    ): ?UserCertification {
        $attempt ??= $this->latestPassedTheory($user, $program);

        if (! $attempt || $attempt->status !== 'passed') {
            return null;
        }

        if (! $this->practicalComplete($user, $program, $attempt)) {
            return null;
        }

        return $this->issue($user, $program, $attempt);
    }

    public function issue(
        User $user,
        CertificationProgram $program,
        CertificationAttempt $attempt,
    ): UserCertification {
        $existing = UserCertification::query()
            ->where('certification_attempt_id', $attempt->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $practicalScore = $this->practicalAverage($user, $attempt);
        $finalScore = $this->finalScore($user, $program, $attempt);
        $verificationCode = (string) Str::uuid();
        $suffix = strtoupper(substr(str_replace('-', '', $verificationCode), 0, 10));
        $issuedAt = now();
        $validMonths = $attempt->valid_months_snapshot ?? $program->valid_months;

        $certificate = UserCertification::query()->create([
            'user_id' => $user->id,
            'certification_program_id' => $program->id,
            'certification_attempt_id' => $attempt->id,

            'recipient_name_snapshot' => $user->name,
            'recipient_unit_snapshot' => $user->unit?->name ?? 'Admin Pusat',
            'recipient_role_snapshot' => $program->roleLabel(),
            'program_code_snapshot' => $attempt->program_code_snapshot ?: $program->code,
            'program_name_snapshot' => $attempt->program_name_snapshot ?: $program->name,
            'program_version_snapshot' => $attempt->program_version_snapshot ?: $program->version,

            'certificate_number' => 'SPMB-'.Str::upper(
                $attempt->program_code_snapshot ?: $program->code
            ).'-'.$issuedAt->format('Y').'-'.$suffix,
            'verification_code' => $verificationCode,
            'score' => $finalScore,
            'theory_score' => (float) $attempt->score,
            'practical_score' => $practicalScore,
            'issued_at' => $issuedAt,
            'expires_at' => $validMonths > 0
                ? $issuedAt->copy()->addMonths($validMonths)
                : null,
            'status' => 'active',
        ]);

        app(AuditTrail::class)->record(
            'certification.issued',
            $certificate,
            actor: $user,
            metadata: [
                'certificate_number' => $certificate->certificate_number,
                'program_version' => $certificate->program_version_snapshot,
                'theory_score' => (float) $attempt->score,
                'practical_score' => $practicalScore,
                'final_score' => $finalScore,
            ],
            description: 'Sertifikat '.$certificate->program_name_snapshot.' diterbitkan',
        );

        if (app(TrainingGovernanceService::class)->certificateArtifactEnabled()) {
            try {
                $certificate = app(CertificateArtifactService::class)->generate($certificate);
            } catch (\Throwable $exception) {
                report($exception);

                app(AuditTrail::class)->record(
                    'certification.artifact_failed',
                    $certificate,
                    actor: $user,
                    metadata: ['error' => $exception->getMessage()],
                    description: 'Pembuatan artifact PDF sertifikat gagal',
                );
            }
        }

        return $certificate;
    }

    public function revoke(
        UserCertification $certificate,
        User $actor,
        string $reason,
        array $metadata = [],
    ): UserCertification {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'revocation_reason' => 'Alasan pencabutan sertifikat wajib diisi.',
            ]);
        }

        if ($certificate->status === 'revoked') {
            return $certificate;
        }

        $certificate->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by_user_id' => $actor->id,
            'revocation_reason' => $reason,
            'revocation_metadata' => $metadata,
        ]);

        app(AuditTrail::class)->record(
            'certification.revoked',
            $certificate,
            actor: $actor,
            metadata: [
                'certificate_number' => $certificate->certificate_number,
                'reason' => $reason,
            ] + $metadata,
            description: 'Sertifikat '.$certificate->certificate_number.' dicabut',
        );

        return $certificate->fresh(['revokedBy']);
    }

    public function finalScore(
        User $user,
        CertificationProgram $program,
        CertificationAttempt $attempt,
    ): float {
        $practicalAverage = $this->practicalAverage($user, $attempt);

        if ($practicalAverage === null) {
            return round((float) $attempt->score, 2);
        }

        $theoryWeight = max(
            0,
            $attempt->theory_weight_snapshot ?? $program->theory_weight,
        );
        $practicalWeight = max(
            0,
            $attempt->practical_weight_snapshot ?? $program->practical_weight,
        );
        $totalWeight = $theoryWeight + $practicalWeight;

        if ($totalWeight === 0) {
            return round(((float) $attempt->score + $practicalAverage) / 2, 2);
        }

        return round(
            (((float) $attempt->score * $theoryWeight)
                + ($practicalAverage * $practicalWeight))
            / $totalWeight,
            2,
        );
    }

    public function practicalAverage(
        User $user,
        CertificationAttempt $attempt,
    ): ?float {
        $scenarioIds = $attempt->hasPracticalRequirementsSnapshot()
            ? collect($attempt->requiredPracticalScenarioIds())
            : $attempt->program->practicalScenarios()
                ->where('is_active', true)
                ->pluck('id');

        if ($scenarioIds->isEmpty()) {
            return null;
        }

        $scores = [];

        foreach ($scenarioIds as $scenarioId) {
            $score = PracticalRun::query()
                ->where('certification_attempt_id', $attempt->id)
                ->where('user_id', $user->id)
                ->where('practical_scenario_id', $scenarioId)
                ->where('status', 'passed')
                ->where('passed', true)
                ->latest('submitted_at')
                ->value('score');

            if ($score === null) {
                return null;
            }

            $scores[] = (float) $score;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    private function assertAttemptPolicy(
        User $user,
        CertificationProgram $program,
    ): void {
        $latestCertificate = UserCertification::query()
            ->where('user_id', $user->id)
            ->where('certification_program_id', $program->id)
            ->latest('issued_at')
            ->first();

        $cycleAttempts = CertificationAttempt::query()
            ->where('user_id', $user->id)
            ->where('certification_program_id', $program->id)
            ->when(
                $latestCertificate?->issued_at,
                fn ($query, $issuedAt) => $query->where('started_at', '>', $issuedAt),
            );

        if ($program->max_attempts
            && (clone $cycleAttempts)->whereIn('status', ['failed', 'in_progress'])->count() >= $program->max_attempts) {
            throw ValidationException::withMessages([
                'certification' => 'Batas percobaan ujian untuk siklus sertifikasi ini telah tercapai.',
            ]);
        }

        if ($program->cooldown_hours > 0) {
            $lastFailed = (clone $cycleAttempts)
                ->where('status', 'failed')
                ->latest('submitted_at')
                ->first();

            if ($lastFailed?->submitted_at) {
                $retryAt = $lastFailed->submitted_at
                    ->copy()
                    ->addHours($program->cooldown_hours);

                if ($retryAt->isFuture()) {
                    throw ValidationException::withMessages([
                        'certification' => 'Ujian dapat diulang setelah '.$retryAt
                            ->timezone(config('app.timezone'))
                            ->format('d/m/Y H:i').'.',
                    ]);
                }
            }
        }
    }

    private function snapshotOptions(
        ?array $options,
        string $type,
        bool $shuffle,
    ): ?array {
        if ($type !== 'single_choice' || ! $options) {
            return $options;
        }

        if (! $shuffle) {
            return $options;
        }

        $keys = array_keys($options);
        shuffle($keys);

        $shuffled = [];
        foreach ($keys as $key) {
            $shuffled[$key] = $options[$key];
        }

        return $shuffled;
    }

    private function expireAttempt(
        CertificationAttempt $attempt,
        User $user,
    ): void {
        if ($attempt->status !== 'in_progress' || ! $attempt->isExpired()) {
            return;
        }

        $attempt->update([
            'score' => 0,
            'status' => 'failed',
            'submitted_at' => now(),
        ]);

        app(AuditTrail::class)->record(
            'certification.exam_expired',
            $attempt,
            actor: $user,
            metadata: ['score' => 0],
            description: 'Ujian teori berakhir karena batas waktu terlewati',
        );
    }

    private function materializeLegacyAttemptQuestions(
        CertificationAttempt $attempt,
    ): void {
        $questions = $attempt->program->questions()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($questions as $index => $question) {
            CertificationAttemptQuestion::query()->firstOrCreate(
                [
                    'certification_attempt_id' => $attempt->id,
                    'sort_order' => $index + 1,
                ],
                [
                    'certification_question_id' => $question->id,
                    'type' => $question->type,
                    'question' => $question->question,
                    'options' => $question->options,
                    'correct_answer' => $question->correct_answer,
                    'explanation' => $question->explanation,
                    'weight' => $question->weight,
                ],
            );
        }

        if (! $attempt->program_code_snapshot) {
            $attempt->update([
                'program_code_snapshot' => $attempt->program->code,
                'program_name_snapshot' => $attempt->program->name,
                'program_version_snapshot' => $attempt->program->version,
                'passing_score_snapshot' => $attempt->program->passing_score,
                'theory_weight_snapshot' => $attempt->program->theory_weight,
                'practical_weight_snapshot' => $attempt->program->practical_weight,
                'practical_passing_score_snapshot' => $attempt->program->practical_passing_score,
                'valid_months_snapshot' => $attempt->program->valid_months,
                'question_count_snapshot' => $questions->count(),
                'required_practical_scenario_ids_snapshot' => $attempt->program
                    ->practicalScenarios()
                    ->where('is_active', true)
                    ->pluck('id')
                    ->values()
                    ->all(),
            ]);
        }
    }
}
