<?php

namespace App\Services;

use App\Models\CertificationAttempt;
use App\Models\PracticalRun;
use App\Models\PracticalRunAction;
use App\Models\PracticalRunAssertion;
use App\Models\PracticalRunEvent;
use App\Models\PracticalRunResult;
use App\Models\PracticalSandboxRecord;
use App\Models\PracticalScenario;
use App\Models\User;
use App\Services\PracticalValidators\PracticalValidatorRegistry;
use App\Services\PracticalValidators\PracticalValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PracticalSandboxService
{
    public function availableScenarios(User $user): Collection
    {
        return PracticalScenario::query()
            ->active()
            ->whereHas('program', fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('target_role', $user->getRoleNames()->all()))
            ->with([
                'program',
                'records',
                'actions' => fn ($query) => $query->where('is_active', true),
                'runs' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->latest('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function latestPassedTheory(
        User $user,
        PracticalScenario $scenario,
    ): ?CertificationAttempt {
        return app(CertificationService::class)
            ->latestPassedTheory($user, $scenario->program);
    }

    public function canStart(User $user, PracticalScenario $scenario): bool
    {
        $theory = $this->latestPassedTheory($user, $scenario);

        if (! $theory) {
            return false;
        }

        $requiredScenarioIds = $theory->hasPracticalRequirementsSnapshot()
            ? $theory->requiredPracticalScenarioIds()
            : $scenario->program
                ->practicalScenarios()
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

        return $user->is_active
            && $scenario->is_active
            && $scenario->program->is_active
            && in_array($scenario->id, $requiredScenarioIds, true)
            && $user->hasRole($scenario->program->target_role)
            && app(CertificationService::class)->eligible($user, $scenario->program);
    }

    public function start(User $user, PracticalScenario $scenario): PracticalRun
    {
        $scenario->loadMissing(
            'program',
            'records',
            'actions',
            'assertions',
        );

        if (! $this->canStart($user, $scenario)) {
            throw ValidationException::withMessages([
                'practical' => 'Ujian teori harus lulus dan scenario harus termasuk paket practical attempt ini.',
            ]);
        }

        $theory = $this->latestPassedTheory($user, $scenario);

        $existing = PracticalRun::query()
            ->where('practical_scenario_id', $scenario->id)
            ->where('certification_attempt_id', $theory->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing->load([
                'runActions',
                'runAssertions',
                'sandboxRecords',
                'events',
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $scenario,
            $theory,
        ): PracticalRun {
            $attemptNo = ((int) PracticalRun::query()
                ->where('practical_scenario_id', $scenario->id)
                ->where('user_id', $user->id)
                ->max('attempt_no')) + 1;

            $run = PracticalRun::query()->create([
                'practical_scenario_id' => $scenario->id,
                'certification_attempt_id' => $theory->id,
                'user_id' => $user->id,
                'scenario_code_snapshot' => $scenario->code,
                'scenario_name_snapshot' => $scenario->name,
                'instructions_snapshot' => $scenario->instructions,
                'time_limit_minutes_snapshot' => $scenario->time_limit_minutes,
                'passing_score_snapshot' => $theory->practical_passing_score_snapshot
                    ?? $scenario->program->practical_passing_score,
                'attempt_no' => $attemptNo,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            foreach ($scenario->records as $definition) {
                PracticalSandboxRecord::query()->create([
                    'practical_run_id' => $run->id,
                    'practical_scenario_record_id' => $definition->id,
                    'entity_type' => $definition->entity_type,
                    'entity_key' => $definition->entity_key,
                    'label' => $definition->label,
                    'original_state' => $definition->initial_state,
                    'state' => $definition->initial_state,
                ]);
            }

            foreach ($scenario->actions->where('is_active', true) as $action) {
                PracticalRunAction::query()->create([
                    'practical_run_id' => $run->id,
                    'practical_scenario_action_id' => $action->id,
                    'code' => $action->code,
                    'label' => $action->label,
                    'target_type' => $action->target_type,
                    'target_key' => $action->target_key,
                    'mutation' => $action->mutation,
                    'allowed_when' => $action->allowed_when,
                    'button_color' => $action->button_color,
                    'requires_confirmation' => $action->requires_confirmation,
                    'sort_order' => $action->sort_order,
                ]);
            }

            foreach ($scenario->assertions->where('is_active', true) as $assertion) {
                PracticalRunAssertion::query()->create([
                    'practical_run_id' => $run->id,
                    'practical_assertion_id' => $assertion->id,
                    'code' => $assertion->code,
                    'name' => $assertion->name,
                    'validator_class' => $assertion->validator_class,
                    'config' => $assertion->config,
                    'points' => $assertion->points,
                    'is_critical' => $assertion->is_critical,
                    'sort_order' => $assertion->sort_order,
                ]);
            }

            app(AuditTrail::class)->record(
                'certification.practical_started',
                $run,
                actor: $user,
                metadata: [
                    'scenario' => $run->scenario_code_snapshot,
                    'attempt_no' => $attemptNo,
                    'action_count' => $run->runActions()->count(),
                    'assertion_count' => $run->runAssertions()->count(),
                ],
                description: 'Ujian praktik '.$run->scenario_name_snapshot.' dimulai',
            );

            return $run->fresh([
                'runActions',
                'runAssertions',
                'sandboxRecords',
                'events',
            ]);
        }, 3);
    }

    public function performAction(
        User $user,
        PracticalRun $run,
        PracticalRunAction $action,
    ): PracticalSandboxRecord {
        return DB::transaction(function () use (
            $user,
            $run,
            $action,
        ): PracticalSandboxRecord {
            $lockedRun = PracticalRun::query()
                ->whereKey($run->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRun->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'practical' => 'Practical run ini sudah selesai.',
                ]);
            }

            if ($lockedRun->isExpired()) {
                throw ValidationException::withMessages([
                    'practical' => 'Waktu practical run telah berakhir. Silakan kirim untuk dinilai.',
                ]);
            }

            if ($action->practical_run_id !== $lockedRun->id) {
                throw ValidationException::withMessages([
                    'practical' => 'Aksi tidak tersedia untuk practical run ini.',
                ]);
            }

            $record = PracticalSandboxRecord::query()
                ->where('practical_run_id', $lockedRun->id)
                ->where('entity_type', $action->target_type)
                ->where('entity_key', $action->target_key)
                ->lockForUpdate()
                ->firstOrFail();

            $state = $record->state;

            foreach ($action->allowed_when ?? [] as $path => $expected) {
                if (! PracticalValue::equivalent(
                    $expected,
                    data_get($state, (string) $path),
                )) {
                    throw ValidationException::withMessages([
                        'practical' => 'Aksi ini tidak dapat dilakukan pada kondisi sandbox saat ini.',
                    ]);
                }
            }

            $before = $state;

            foreach ($action->mutation ?? [] as $path => $value) {
                data_set(
                    $state,
                    (string) $path,
                    PracticalValue::normalize($value),
                );
            }

            $record->update(['state' => $state]);

            PracticalRunEvent::query()->create([
                'practical_run_id' => $lockedRun->id,
                'practical_scenario_action_id' => $action->practical_scenario_action_id,
                'practical_run_action_id' => $action->id,
                'action_code' => $action->code,
                'target_type' => $action->target_type,
                'target_key' => $action->target_key,
                'before_state' => $before,
                'after_state' => $state,
                'metadata' => ['label' => $action->label],
                'created_at' => now(),
            ]);

            return $record->fresh();
        }, 3);
    }

    public function submit(User $user, PracticalRun $run): PracticalRun
    {
        $evaluated = DB::transaction(function () use (
            $user,
            $run,
        ): PracticalRun {
            $locked = PracticalRun::query()
                ->whereKey($run->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->with([
                    'scenario.program',
                    'runAssertions',
                    'sandboxRecords',
                    'events',
                    'theoryAttempt',
                ])
                ->firstOrFail();

            if ($locked->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'practical' => 'Practical run ini sudah dinilai.',
                ]);
            }

            $assertions = $locked->runAssertions;

            if ($assertions->isEmpty()) {
                throw ValidationException::withMessages([
                    'practical' => 'Practical run tidak memiliki snapshot validator.',
                ]);
            }

            $totalPoints = 0.0;
            $earnedPoints = 0.0;
            $criticalFailed = false;

            foreach ($assertions as $assertion) {
                $points = max(0.01, (float) $assertion->points);
                $validation = app(PracticalValidatorRegistry::class)
                    ->resolve($assertion->validator_class)
                    ->validate($locked, $assertion);

                $earned = $validation->passed ? $points : 0.0;
                $totalPoints += $points;
                $earnedPoints += $earned;

                if ($assertion->is_critical && ! $validation->passed) {
                    $criticalFailed = true;
                }

                PracticalRunResult::query()->updateOrCreate(
                    [
                        'practical_run_id' => $locked->id,
                        'practical_run_assertion_id' => $assertion->id,
                    ],
                    [
                        'practical_assertion_id' => $assertion->practical_assertion_id,
                        'passed' => $validation->passed,
                        'score' => $earned,
                        'expected' => $validation->expected,
                        'actual' => $validation->actual,
                        'feedback' => $validation->feedback,
                    ],
                );
            }

            $score = round(($earnedPoints / $totalPoints) * 100, 2);
            $passingScore = $locked->passing_score_snapshot
                ?? $locked->theoryAttempt?->practical_passing_score_snapshot
                ?? $locked->scenario->program->practical_passing_score;

            $passed = ! $criticalFailed && $score >= $passingScore;

            $locked->update([
                'status' => $passed ? 'passed' : 'failed',
                'score' => $score,
                'passed' => $passed,
                'submitted_at' => now(),
            ]);

            app(AuditTrail::class)->record(
                'certification.practical_submitted',
                $locked,
                actor: $user,
                metadata: [
                    'scenario' => $locked->scenario_code_snapshot,
                    'score' => $score,
                    'passing_score' => $passingScore,
                    'passed' => $passed,
                    'critical_failed' => $criticalFailed,
                ],
                description: 'Ujian praktik '.$locked->displayName().' dinilai',
            );

            return $locked->fresh([
                'scenario.program',
                'results.runAssertion',
                'results.assertion',
                'theoryAttempt',
            ]);
        }, 3);

        if ($evaluated->passed) {
            app(CertificationService::class)->issueIfComplete(
                $user,
                $evaluated->scenario->program,
                $evaluated->theoryAttempt,
            );
        }

        return $evaluated->fresh([
            'scenario.program',
            'results.runAssertion',
            'results.assertion',
            'theoryAttempt',
        ]);
    }
}
