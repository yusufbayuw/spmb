<?php

namespace App\Services;

use App\Models\CertificationAnswer;
use App\Models\CertificationAttempt;
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
            ->active()->forUser($user)
            ->with([
                'trainingProgram',
                'questions' => fn ($query) => $query->where('is_active', true),
                'practicalScenarios' => fn ($query) => $query->where('is_active', true),
                'attempts' => fn ($query) => $query->where('user_id', $user->id)->latest('id'),
                'certifications' => fn ($query) => $query->where('user_id', $user->id)->latest('issued_at'),
            ])
            ->orderBy('sort_order')->orderBy('name')->get();
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

        $scenarioIds = $program->practicalScenarios()
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
            throw ValidationException::withMessages(['certification' => 'Syarat training untuk ujian sertifikasi belum terpenuhi.']);
        }

        if (UserCertification::query()
            ->where('user_id', $user->id)
            ->where('certification_program_id', $program->id)
            ->valid()->exists()) {
            throw ValidationException::withMessages(['certification' => 'Sertifikasi ini masih aktif dan belum memerlukan ujian ulang.']);
        }

        if ($this->latestPassedTheory($user, $program)) {
            throw ValidationException::withMessages([
                'certification' => 'Ujian teori sudah lulus. Selesaikan practical exam yang masih diperlukan.',
            ]);
        }

        if (! $program->questions()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['certification' => 'Program sertifikasi belum memiliki soal aktif.']);
        }

        $existing = CertificationAttempt::query()
            ->where('certification_program_id', $program->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest('id')->first();

        if ($existing) {
            return $existing->load('program.questions');
        }

        $attemptNo = ((int) CertificationAttempt::query()
            ->where('certification_program_id', $program->id)
            ->where('user_id', $user->id)
            ->max('attempt_no')) + 1;

        return CertificationAttempt::query()->create([
            'certification_program_id' => $program->id,
            'user_id' => $user->id,
            'attempt_no' => $attemptNo,
            'status' => 'in_progress',
            'started_at' => now(),
        ])->load('program.questions');
    }

    public function submit(CertificationAttempt $attempt, User $user, array $answers): CertificationAttempt
    {
        $evaluated = DB::transaction(function () use ($attempt, $user, $answers): CertificationAttempt {
            $locked = CertificationAttempt::query()
                ->whereKey($attempt->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'in_progress') {
                throw ValidationException::withMessages(['certification' => 'Ujian ini sudah diselesaikan.']);
            }

            $program = $locked->program;
            if (! $this->eligible($user, $program)) {
                throw ValidationException::withMessages(['certification' => 'Pengguna tidak lagi memenuhi syarat ujian ini.']);
            }

            $questions = $program->questions()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
            if ($questions->isEmpty()) {
                throw ValidationException::withMessages(['certification' => 'Tidak ada soal aktif untuk dinilai.']);
            }

            $totalWeight = 0.0;
            $earned = 0.0;

            foreach ($questions as $question) {
                $weight = max(0.01, (float) $question->weight);
                $answer = array_key_exists($question->id, $answers) ? trim((string) $answers[$question->id]) : null;
                $isCorrect = $answer !== null && hash_equals((string) $question->correct_answer, $answer);
                $answerScore = $isCorrect ? $weight : 0.0;
                $totalWeight += $weight;
                $earned += $answerScore;

                CertificationAnswer::query()->updateOrCreate(
                    ['certification_attempt_id' => $locked->id, 'certification_question_id' => $question->id],
                    ['answer' => $answer, 'is_correct' => $isCorrect, 'score' => $answerScore],
                );
            }

            $score = round(($earned / $totalWeight) * 100, 2);
            $passed = $score >= $program->passing_score;
            $locked->update(['score' => $score, 'status' => $passed ? 'passed' : 'failed', 'submitted_at' => now()]);

            app(AuditTrail::class)->record(
                'certification.exam_submitted',
                $locked,
                actor: $user,
                metadata: ['score' => $score, 'passed' => $passed],
                description: 'Ujian '.$program->name.' diselesaikan',
            );

            return $locked->fresh(['program', 'answers', 'certification']);
        }, 3);

        if ($evaluated->status === 'passed') {
            $this->issueIfComplete($user, $evaluated->program, $evaluated);
        }

        return $evaluated->fresh(['program', 'answers', 'certification']);
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

    public function issue(User $user, CertificationProgram $program, CertificationAttempt $attempt): UserCertification
    {
        $existing = UserCertification::query()->where('certification_attempt_id', $attempt->id)->first();
        if ($existing) {
            return $existing;
        }

        $finalScore = $this->finalScore($user, $program, $attempt);
        $verificationCode = (string) Str::uuid();
        $suffix = strtoupper(substr(str_replace('-', '', $verificationCode), 0, 10));
        $issuedAt = now();

        $certificate = UserCertification::query()->create([
            'user_id' => $user->id,
            'certification_program_id' => $program->id,
            'certification_attempt_id' => $attempt->id,
            'certificate_number' => 'SPMB-'.Str::upper($program->code).'-'.$issuedAt->format('Y').'-'.$suffix,
            'verification_code' => $verificationCode,
            'score' => $finalScore,
            'issued_at' => $issuedAt,
            'expires_at' => $program->valid_months > 0 ? $issuedAt->copy()->addMonths($program->valid_months) : null,
            'status' => 'active',
        ]);

        app(AuditTrail::class)->record(
            'certification.issued',
            $certificate,
            actor: $user,
            metadata: [
                'certificate_number' => $certificate->certificate_number,
                'theory_score' => (float) $attempt->score,
                'final_score' => $finalScore,
            ],
            description: 'Sertifikat '.$program->name.' diterbitkan',
        );

        return $certificate;
    }

    public function finalScore(User $user, CertificationProgram $program, CertificationAttempt $attempt): float
    {
        $scenarioIds = $program->practicalScenarios()
            ->where('is_active', true)
            ->pluck('id');

        if ($scenarioIds->isEmpty()) {
            return round((float) $attempt->score, 2);
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
                return round((float) $attempt->score, 2);
            }

            $scores[] = (float) $score;
        }

        $practicalAverage = array_sum($scores) / count($scores);
        $theoryWeight = max(0, $program->theory_weight);
        $practicalWeight = max(0, $program->practical_weight);
        $totalWeight = $theoryWeight + $practicalWeight;

        if ($totalWeight === 0) {
            return round(((float) $attempt->score + $practicalAverage) / 2, 2);
        }

        return round(
            (((float) $attempt->score * $theoryWeight) + ($practicalAverage * $practicalWeight))
            / $totalWeight,
            2,
        );
    }
}
