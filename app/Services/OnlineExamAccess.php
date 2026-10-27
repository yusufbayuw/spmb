<?php

namespace App\Services;

use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OnlineExamAccess
{
    public function canManageUnit(?User $actor, int $unitId): bool
    {
        if (! $actor || ! $actor->is_active || ! $actor->hasOperationalUnitAccess()) {
            return false;
        }

        if (! $actor->isAdmin() && ! ($actor->isAdminUnit() && (int) $actor->unit_id === $unitId)) {
            return false;
        }

        return \App\Models\Unit::query()->operational()->whereKey($unitId)->exists();
    }

    public function canManage(?User $actor, OnlineExam $exam): bool
    {
        return $this->canManageUnit($actor, (int) $exam->unit_id);
    }

    public function staffScope(Builder $query, ?User $actor, string $column = 'unit_id'): Builder
    {
        if (! $actor || ! $actor->is_active
            || (! $actor->isAdmin() && ! $actor->isAdminUnit())) {
            return $query->whereRaw('1 = 0');
        }

        if ($actor->isAdmin()) {
            return $query;
        }

        return $query->where($column, (int) $actor->unit_id);
    }

    public function canSit(?User $actor, OnlineExamAssignment $assignment): bool
    {
        return $actor !== null && $actor->is_active
            && $actor->isUser()
            && $assignment->is_active
            && (int) $actor->id === (int) $assignment->user_id
            && $assignment->exam?->isOpen();
    }

    public function assertParticipant(?User $actor, OnlineExamAssignment $assignment): void
    {
        abort_unless($this->canSit($actor, $assignment), 403);
    }
}
