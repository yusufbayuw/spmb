<?php

namespace App\Services;

use App\Models\CertificationAttempt;
use App\Models\PracticalRun;
use App\Models\PracticalRunEvent;
use App\Models\PracticalRunResult;
use App\Models\PracticalSandboxRecord;
use App\Models\PracticalScenario;
use App\Models\PracticalScenarioAction;
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
                'runs' => fn ($query) => $query->where('user_id', $user->id)->latest('id'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function latestPassedTheory(User $user, PracticalScenario $scenario): ?CertificationAttempt
    {
        return CertificationAttempt::query()
            ->where('certification_program_id', $scenario->certification_program_id)
            ->where('user_id', $user->id)
            ->where('status', 'passed')
            ->latest('submitted_at')
            ->latest('id')
            ->first();
    }

    public function canStart(User $user, PracticalScenario $scenario): bool
    {
        return $user->is_active
            && $scenario->is_active
            && $scenario->program->is_active
            && $user->hasRole($scenario->program->target_role)
            && app(CertificationService::class)->eligible($user, $scenario->program)
            && $this->latestPassedTheory($user, $scenario) !== null;
    }

    public function start(User $user, PracticalScenario $scenario): PracticalRun
    {
        $scenario->loadMissing('program', 'records');

        if (! $this->canStart($user, $scenario)) {
            throw ValidationException::withMessages([
                'practical' => 'Ujian teori harus lulus sebelum practical scenario dapat dimulai.',
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
            return $existing->load(['scenario.actions', 'sandboxRecords', 'events']);
        }

        return DB::transaction(function () use ($user, $scenario, $theory): PracticalRun {
            $attemptNo = ((int) PracticalRun::query()
                ->where('practical_scenario_id', $scenario->id)
                ->where('user_id', $user->id)
                ->max('attempt_no')) + 1;

            $run = PracticalRun::query()->create([
                'practical_scenario_id' => $scenario->id,
                'certification_attempt_id' => $theory->id,
                'user_id' => $user->id,
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

            app(AuditTrail::class)->record(
                'certification.practical_started',
                $run,
                actor: $user,
                metadata: ['scenario' => $scenario->code, 'attempt_no' => $attemptNo],
                description: 'Ujian praktik '.$scenario->name.' dimulai',
            );

            return $run->fresh(['scenario.actions', 'sandboxRecords', 'events']);
        }, 3);
    }

    public function performAction(User $user, PracticalRun $run, PracticalScenarioAction $action): PracticalSandboxRecord
    {
        return DB::transaction(function () use ($user, $run, $action): PracticalSandboxRecord {
            $lockedRun = PracticalRun::query()
                ->whereKey($run->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->with('scenario')
                ->firstOrFail();

            if ($lockedRun->status !== 'in_progress') {
                throw ValidationException::withMessages(['practical' => 'Practical run ini sudah selesai.']);
            }

            if ($lockedRun->isExpired()) {
                throw ValidationException::withMessages(['practical' => 'Waktu practical run telah berakhir. Silakan kirim untuk dinilai.']);
            }

            if (! $action->is_active || $action->practical_scenario_id !== $lockedRun->practical_scenario_id) {
                throw ValidationException::withMessages(['practical' => 'Aksi tidak tersedia untuk scenario ini.']);
            }

            $record = PracticalSandboxRecord::query()
                ->where('practical_run_id', $lockedRun->id)
                ->where('entity_type', $action->target_type)
                ->where('entity_key', $action->target_key)
                ->lockForUpdate()
                ->firstOrFail();

            $state = $record->state;

            foreach ($action->allowed_when ?? [] as $path => $expected) {
                if (! PracticalValue::equivalent($expected, data_get($state, (string) $path))) {
                    throw ValidationException::withMessages([
                        'practical' => 'Aksi ini tidak dapat dilakukan pada kondisi sandbox saat ini.',
                    ]);
                }
            }

            $before = $state;
            foreach ($action->mutation ?? [] as $path => $value) {
                data_set($state, (string) $path, PracticalValue::normalize($value));
            }

            $record->update(['state' => $state]);

            PracticalRunEvent::query()->create([
                'practical_run_id' => $lockedRun->id,
                'practical_scenario_action_id' => $action->id,
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
        $evaluated = DB::transaction(function () use ($user, $run): PracticalRun {
            $locked = PracticalRun::query()
                ->whereKey($run->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->with(['scenario.program', 'scenario.assertions', 'sandboxRecords', 'events'])
                ->firstOrFail();

            if ($locked->status !== 'in_progress') {
                throw ValidationException::withMessages(['practical' => 'Practical run ini sudah dinilai.']);
            }

            $assertions = $locked->scenario->assertions
                ->where('is_active', true);

            if ($assertions->isEmpty()) {
                throw ValidationException::withMessages(['practical' => 'Scenario belum memiliki validator aktif.']);
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
                $criticalFailed = $criticalFailed || ($assertion->is_critical && ! $validation->passed);

                PracticalRunResult::query()->updateOrCreate(
                    [
                        'practical_run_id' => $locked->id,
                        'practical_assertion_id' => $assertion->id,
                    ],
                    [
                        'passed' => $validation->passed,
                        'score' => $earned,
                        'expected' => $validation->expected,
                        'actual' => $validation->actual,
                        'feedback' => $validation->feedback,
                    ],
                );
            }

            $score = round(($earnedPoints / $totalPoints) * 100, 2);
            $passed = ! $criticalFailed
                && $score >= $locked->scenario->program->practical_passing_score;

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
                    'scenario' => $locked->scenario->code,
                    'score' => $score,
                    'passed' => $passed,
                    'critical_failed' => $criticalFailed,
                ],
                description: 'Ujian praktik '.$locked->scenario->name.' dinilai',
            );

            return $locked->fresh(['scenario.program', 'results.assertion', 'theoryAttempt']);
        }, 3);

        if ($evaluated->passed) {
            app(CertificationService::class)->issueIfComplete(
                $user,
                $evaluated->scenario->program,
                $evaluated->theoryAttempt,
            );
        }

        return $evaluated->fresh(['scenario.program', 'results.assertion', 'theoryAttempt']);
    }
}
