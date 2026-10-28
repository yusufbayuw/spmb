<?php

namespace App\Services;

use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineExamBulkAssignmentService
{
    public const MAX_CANDIDATES = 500;

    /**
     * Preview does not mutate assignments or registration workflow.
     *
     * @return array{eligible:int,new:int,already_assigned:int,revoked:int,signature:string,sample:list<array{id:int,name:string,number:string}>}
     */
    public function preview(OnlineExam $exam, int $openingId, ?int $pathwayId, User $actor): array
    {
        $opening = $this->assertScope($exam, $openingId, $pathwayId, $actor);
        $candidateIds = $this->candidateIds($exam, $opening, $pathwayId);

        if (count($candidateIds) > self::MAX_CANDIDATES) {
            throw ValidationException::withMessages([
                'opening' => 'Ditemukan lebih dari 500 akun. Persempit pilihan dengan jalur atau pembukaan pendaftaran yang lebih spesifik.',
            ]);
        }

        $assigned = OnlineExamAssignment::query()
            ->where('online_exam_id', $exam->id)
            ->whereIn('user_id', $candidateIds)
            ->get(['user_id', 'is_active'])
            ->keyBy('user_id');

        $active = 0;
        $revoked = 0;
        foreach ($candidateIds as $id) {
            $state = $assigned->get($id);
            if ($state?->is_active) {
                $active++;
            } elseif ($state !== null) {
                $revoked++;
            }
        }

        $sample = Registration::query()
            ->where('unit_id', $exam->unit_id)
            ->where('registration_opening_id', $opening->id)
            ->when($pathwayId !== null, fn (Builder $q) => $q->where('registration_pathway_id', $pathwayId))
            ->whereIn('user_id', $candidateIds)
            ->orderBy('id')
            ->limit(80)
            ->get(['id', 'user_id', 'full_name', 'registration_number'])
            ->unique('user_id')->take(10)
            ->map(fn (Registration $reg): array => [
                'id' => (int) $reg->user_id,
                'name' => (string) $reg->full_name,
                'number' => (string) $reg->registration_number,
            ])->values()->all();

        return [
            'eligible' => count($candidateIds),
            'new' => count($candidateIds) - $active - $revoked,
            'already_assigned' => $active,
            'revoked' => $revoked,
            'signature' => $this->signature($exam, $opening, $pathwayId, $candidateIds, $assigned),
            'sample' => $sample,
        ];
    }

    /**
     * Recompute exactly the same cohort and assignment states in a transaction.
     * Fail closed if the operator's preview is stale.
     *
     * @return array{created:int,already_assigned:int,revoked:int}
     */
    public function execute(
        OnlineExam $exam,
        int $openingId,
        ?int $pathwayId,
        string $previewSignature,
        User $actor,
    ): array {
        return DB::transaction(function () use ($exam, $openingId, $pathwayId, $previewSignature, $actor): array {
            $exam = OnlineExam::query()->lockForUpdate()->findOrFail($exam->id);
            $opening = RegistrationOpening::query()->lockForUpdate()->findOrFail($openingId);

            $this->assertScope($exam, $opening->id, $pathwayId, $actor);
            $ids = $this->candidateIds($exam, $opening, $pathwayId);

            if (count($ids) > self::MAX_CANDIDATES) {
                throw ValidationException::withMessages([
                    'opening' => 'Jumlah peserta lebih dari 500. Batalkan dan perkecil cakupan.',
                ]);
            }

            $assigned = OnlineExamAssignment::query()
                ->where('online_exam_id', $exam->id)
                ->whereIn('user_id', $ids)
                ->lockForUpdate()
                ->get(['user_id', 'is_active'])
                ->keyBy('user_id');

            if (! hash_equals(
                $this->signature($exam, $opening, $pathwayId, $ids, $assigned),
                $previewSignature,
            )) {
                throw ValidationException::withMessages([
                    'preview' => 'Data peserta atau penugasan berubah. Tampilkan pratinjau kembali sebelum menugaskan.',
                ]);
            }

            $result = ['created' => 0, 'already_assigned' => 0, 'revoked' => 0];

            foreach ($ids as $userId) {
                $current = $assigned->get($userId);
                if ($current?->is_active) {
                    $result['already_assigned']++;

                    continue;
                }
                if ($current !== null) {
                    // Never reactivate an intentionally revoked assignment.
                    $result['revoked']++;

                    continue;
                }

                $participant = User::query()->findOrFail($userId);
                app(OnlineExamEngine::class)->assign($exam, $participant, $actor);
                $result['created']++;
            }

            app(AuditTrail::class)->record(
                'online_exam.bulk_assigned',
                $exam,
                actor: $actor,
                unitId: $exam->unit_id,
                metadata: [
                    'opening_id' => $opening->id,
                    'pathway_id' => $pathwayId,
                    'created_count' => $result['created'],
                    'existing_count' => $result['already_assigned'],
                    'revoked_count' => $result['revoked'],
                ],
                description: 'Petugas menetapkan peserta Tes Online dari pembukaan pendaftaran.',
            );

            return $result;
        }, 3);
    }

    private function assertScope(OnlineExam $exam, int $openingId, ?int $pathwayId, User $actor): RegistrationOpening
    {
        abort_unless(app(OnlineExamAccess::class)->canManage($actor, $exam), 403);

        if (! $exam->is_published) {
            throw ValidationException::withMessages([
                'exam' => 'Paket harus diterbitkan sebelum penugasan peserta massal.',
            ]);
        }

        $opening = RegistrationOpening::query()
            ->whereKey($openingId)
            ->where('unit_id', $exam->unit_id)
            ->where('status', '!=', 'archived')
            ->whereNull('archived_at')
            ->first();

        if (! $opening || ($exam->study_program_id !== null
            && (int) $exam->study_program_id !== (int) $opening->study_program_id)) {
            throw ValidationException::withMessages([
                'opening' => 'Pembukaan pendaftaran harus cocok dengan unit dan prodi paket Tes Online.',
            ]);
        }

        if ($pathwayId !== null && ! RegistrationPathway::query()
            ->whereKey($pathwayId)
            ->where('unit_id', $exam->unit_id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'pathway' => 'Jalur pendaftaran tidak aktif atau berasal dari unit lain.',
            ]);
        }

        return $opening;
    }

    /** @return list<int> */
    private function candidateIds(OnlineExam $exam, RegistrationOpening $opening, ?int $pathwayId): array
    {
        return Registration::query()
            ->where('unit_id', $exam->unit_id)
            ->where('registration_opening_id', $opening->id)
            ->when($pathwayId !== null, fn (Builder $q) => $q->where('registration_pathway_id', $pathwayId))
            ->where('lifecycle_status', 'active')
            ->whereNotNull('submitted_at')
            ->whereNotNull('user_id')
            ->whereHas('user', fn (Builder $q) => $q->where('is_active', true)
                ->whereHas('roles', fn (Builder $role) => $role->where('name', 'pendaftar')))
            ->distinct()
            ->orderBy('user_id')
            ->limit(self::MAX_CANDIDATES + 1)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)->all();
    }

    private function signature(
        OnlineExam $exam,
        RegistrationOpening $opening,
        ?int $pathwayId,
        array $ids,
        \Illuminate\Support\Collection $assigned,
    ): string {
        $states = [];
        foreach ($ids as $id) {
            $states[] = $id.':'.match (true) {
                $assigned->get($id) === null => 'new',
                $assigned->get($id)->is_active => 'active',
                default => 'revoked',
            };
        }

        return hash('sha256', implode('|', [
            $exam->id,
            $opening->id,
            $pathwayId ?? 'all',
            implode(',', $states),
        ]));
    }
}
