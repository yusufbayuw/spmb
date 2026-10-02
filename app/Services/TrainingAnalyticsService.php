<?php

namespace App\Services;

use App\Models\CertificationAttempt;
use App\Models\TrainingEnrollment;
use App\Models\TrainingModuleAttempt;
use App\Models\TrainingProgram;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserCertification;

class TrainingAnalyticsService
{
    public function dashboard(User $viewer): array
    {
        $userIds = $this->staffUserIds($viewer);

        $enrollments = TrainingEnrollment::query()->whereIn('user_id', $userIds);
        $certifications = UserCertification::query()->whereIn('user_id', $userIds);

        $overview = [
            'staff' => count($userIds),
            'enrollments' => (clone $enrollments)->count(),
            'training_completed' => (clone $enrollments)->where('status', 'completed')->count(),
            'active_certifications' => (clone $certifications)->valid()->count(),
            'expiring_30_days' => (clone $certifications)
                ->where('status', 'active')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])
                ->count(),
            'revoked' => (clone $certifications)->where('status', 'revoked')->count(),
        ];

        $programs = TrainingProgram::query()
            ->with('certificationPrograms')
            ->orderBy('sort_order')
            ->get()
            ->map(function (TrainingProgram $program) use ($userIds): array {
                $enrolled = TrainingEnrollment::query()
                    ->where('training_program_id', $program->id)
                    ->whereIn('user_id', $userIds)
                    ->count();
                $completed = TrainingEnrollment::query()
                    ->where('training_program_id', $program->id)
                    ->whereIn('user_id', $userIds)
                    ->where('status', 'completed')
                    ->count();
                $certProgramIds = $program->certificationPrograms->pluck('id');

                return [
                    'program' => $program->name,
                    'code' => $program->code,
                    'version' => $program->version,
                    'enrolled' => $enrolled,
                    'completed' => $completed,
                    'completion_rate' => $enrolled > 0 ? round(($completed / $enrolled) * 100, 1) : 0,
                    'active_certifications' => UserCertification::query()
                        ->whereIn('user_id', $userIds)
                        ->whereIn('certification_program_id', $certProgramIds)
                        ->valid()
                        ->count(),
                    'theory_pass_rate' => $this->passRate(
                        CertificationAttempt::query()
                            ->whereIn('user_id', $userIds)
                            ->whereIn('certification_program_id', $certProgramIds)
                            ->whereIn('status', ['passed', 'failed']),
                    ),
                ];
            })
            ->all();

        $weakModules = TrainingModuleAttempt::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', ['passed', 'failed'])
            ->with('assessment.module.program')
            ->get()
            ->groupBy('training_module_assessment_id')
            ->map(function ($attempts): array {
                $first = $attempts->first();
                $count = $attempts->count();
                $failed = $attempts->where('status', 'failed')->count();

                return [
                    'module' => $first->assessment->module->title,
                    'program' => $first->assessment->module->program->code,
                    'attempts' => $count,
                    'average_score' => round((float) $attempts->avg('score'), 1),
                    'fail_rate' => $count > 0 ? round(($failed / $count) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('fail_rate')
            ->take(10)
            ->values()
            ->all();

        return [
            'overview' => $overview,
            'programs' => $programs,
            'coverage' => $this->coverage($viewer),
            'weak_modules' => $weakModules,
        ];
    }

    private function coverage(User $viewer): array
    {
        $units = Unit::query()
            ->when($viewer->isAdminUnit(), fn ($query) => $query->whereKey($viewer->unit_id))
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($units as $unit) {
            foreach (['admin_unit' => 'SCUA', 'tu' => 'SCAO'] as $role => $code) {
                $users = User::query()
                    ->where('is_active', true)
                    ->where('unit_id', $unit->id)
                    ->whereHas('roles', fn ($query) => $query->where('name', $role))
                    ->pluck('id');

                if ($users->isEmpty()) {
                    continue;
                }

                $certified = UserCertification::query()
                    ->whereIn('user_id', $users)
                    ->whereHas('program', fn ($query) => $query->where('code', $code))
                    ->valid()
                    ->distinct('user_id')
                    ->count('user_id');

                $rows[] = [
                    'unit' => $unit->name,
                    'role' => $role === 'admin_unit' ? 'Admin Unit' : 'TU / Operator',
                    'required' => $code,
                    'staff' => $users->count(),
                    'certified' => $certified,
                    'coverage' => round(($certified / $users->count()) * 100, 1),
                ];
            }
        }

        return $rows;
    }

    private function staffUserIds(User $viewer): array
    {
        return User::query()
            ->where('is_active', true)
            ->when($viewer->isAdminUnit(), fn ($query) => $query->where('unit_id', $viewer->unit_id))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super_admin', 'admin_unit', 'tu']))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function passRate($query): float
    {
        $total = (clone $query)->count();

        if ($total === 0) {
            return 0;
        }

        $passed = (clone $query)->where('status', 'passed')->count();

        return round(($passed / $total) * 100, 1);
    }
}
