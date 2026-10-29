<?php

namespace App\Services;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\OnlineExamAttempt;
use App\Models\OnlineExamResultTransfer;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineExamResultTransferService
{
    /**
     * Only show pending results for an active registration owned by the exam participant.
     * The actual transfer rechecks every condition under database locks.
     *
     * @return array<int, string>
     */
    public function eligibleRegistrations(OnlineExamAttempt $attempt, User $actor): array
    {
        $attempt->loadMissing('exam.admissionTest');
        $this->authorize($attempt, $actor);
        $test = $attempt->exam->admissionTest;

        if ($attempt->status !== 'graded' || $attempt->percentage === null || ! $test
            || $attempt->transfer()->exists()) {
            return [];
        }

        return Registration::query()
            ->with(['opening', 'configuration', 'unit'])
            ->where('user_id', $attempt->user_id)
            ->where('unit_id', $attempt->exam->unit_id)
            ->where('lifecycle_status', 'active')
            ->where('current_stage', 'tests')
            ->whereNotNull('submitted_at')
            ->whereHas('testResults', fn (Builder $q): Builder => $q
                ->where('admission_test_id', $test->id)
                ->whereIn('status', ['unbooked', 'scheduled'])
                ->where('result', 'pending')
                ->whereNull('assessed_at'))
            ->orderBy('id')
            ->get()
            ->filter(fn (Registration $registration): bool => $this->matches($registration, $test))
            ->mapWithKeys(fn (Registration $registration): array => [
                $registration->id => ($registration->registration_number ?: $registration->uuid)
                    .' · '.$registration->full_name,
            ])->all();
    }

    public function transfer(OnlineExamAttempt $attempt, int $registrationId, User $actor): OnlineExamResultTransfer
    {
        return DB::transaction(function () use ($attempt, $registrationId, $actor): OnlineExamResultTransfer {
            $attempt = OnlineExamAttempt::query()
                ->with(['exam.admissionTest', 'assignment'])
                ->lockForUpdate()->findOrFail($attempt->id);
            $this->authorize($attempt, $actor);

            $exam = $attempt->exam;
            $test = $exam->admissionTest;
            if (! $test || ! $test->is_active || $attempt->status !== 'graded'
                || $attempt->percentage === null || $attempt->passed === null
                || ! $attempt->assignment?->is_active) {
                throw ValidationException::withMessages([
                    'transfer' => 'Paket tes belum dipetakan, akses sudah dicabut, atau penilaian belum final.',
                ]);
            }

            if (OnlineExamResultTransfer::query()->where('online_exam_attempt_id', $attempt->id)->exists()) {
                throw ValidationException::withMessages([
                    'transfer' => 'Percobaan ujian ini telah disahkan sebelumnya.',
                ]);
            }

            $registration = Registration::query()
                ->with(['unit', 'opening', 'configuration', 'pathway'])
                ->lockForUpdate()->findOrFail($registrationId);

            if ((int) $registration->user_id !== (int) $attempt->user_id
                || (int) $registration->unit_id !== (int) $exam->unit_id
                || ! $registration->isOperational() || $registration->current_stage !== 'tests'
                || $registration->submitted_at === null || ! $this->matches($registration, $test)) {
                throw ValidationException::withMessages([
                    'registration_id' => 'Pendaftaran bukan milik peserta atau tidak memenuhi syarat integrasi CBT.',
                ]);
            }

            $result = AdmissionTestResult::query()
                ->where('registration_id', $registration->id)
                ->where('admission_test_id', $test->id)
                ->lockForUpdate()->first();

            if (! $result || ! in_array($result->status, ['unbooked', 'scheduled'], true)
                || $result->result !== 'pending' || $result->assessed_at !== null
                || OnlineExamResultTransfer::query()->where('admission_test_result_id', $result->id)->exists()) {
                throw ValidationException::withMessages([
                    'transfer' => 'Hasil tes sudah final, tidak tersedia, atau pernah menerima nilai CBT.',
                ]);
            }

            $definition = collect($registration->configuredTests())
                ->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === (int) $test->id);

            $percentage = round((float) $attempt->percentage, 2);
            $type = (string) ($definition['result_type'] ?? $test->result_type);
            $threshold = array_key_exists('passing_score', $definition)
                ? $definition['passing_score'] : $test->passing_score;
            $passed = $threshold !== null
                ? $percentage >= (float) $threshold
                : (bool) $attempt->passed;
            $decision = $passed ? 'pass' : 'fail';

            // Use the existing workflow service, including its mandatory-test checks,
            // score aggregation, and only the stage transitions it already allows.
            app(RegistrationWorkflowService::class)->recordTestResult($result, $actor, [
                'status' => 'completed',
                'score' => $type === 'pass_fail' ? null : $percentage,
                'result' => $decision,
            ]);

            $transfer = OnlineExamResultTransfer::query()->create([
                'online_exam_attempt_id' => $attempt->id,
                'admission_test_result_id' => $result->id,
                'approved_by' => $actor->id,
                'source_percentage' => $percentage,
                'result' => $decision,
            ]);

            app(AuditTrail::class)->record(
                'online_exam.result_transferred',
                $transfer,
                newValues: ['result' => $decision, 'percentage' => $percentage],
                metadata: [
                    'online_exam_attempt_id' => $attempt->id,
                    'admission_test_id' => $test->id,
                    'admission_test_result_id' => $result->id,
                ],
                actor: $actor,
                unitId: $exam->unit_id,
                registrationId: $registration->id,
                description: 'Staf mengesahkan nilai CBT untuk alur hasil tes pendaftaran.',
            );

            return $transfer;
        }, 3);
    }

    private function authorize(OnlineExamAttempt $attempt, User $actor): void
    {
        abort_unless(app(OnlineExamAccess::class)->canManage($actor, $attempt->exam)
            && $actor->can('record_result_admissiontestresult'), 403);
    }

    private function matches(Registration $registration, AdmissionTest $test): bool
    {
        if (! $test->is_active || (int) $test->unit_id !== (int) $registration->unit_id
            || ! $test->appliesToStudyProgram($registration->opening?->study_program_id)) {
            return false;
        }

        return collect($registration->configuredTests())
            ->contains(fn (array $item): bool => (int) ($item['id'] ?? 0) === (int) $test->id);
    }
}
