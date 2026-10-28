<?php

namespace App\Services;

use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\OnlineExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OnlineExamMonitoringService
{
    public const PAGE_SIZE = 25;

    /**
     * Polling-only, read-only snapshot. A displayed "in progress" state means
     * an unexpired server attempt, NOT proof that a browser is connected.
     *
     * @return array{exam:string,as_of:string,stats:array<string,int>,rows:list<array<string,mixed>>,page:int,last_page:int,total_rows:int}
     */
    public function snapshot(
        OnlineExam $exam,
        User $actor,
        int $page = 1,
        string $search = '',
        string $status = 'all',
    ): array {
        abort_unless(app(OnlineExamAccess::class)->canManage($actor, $exam), 403);

        $clock = now();
        $latestIds = DB::table('online_exam_attempts')
            ->select('online_exam_assignment_id')
            ->selectRaw('MAX(id) as latest_id')
            ->where('online_exam_id', $exam->id)
            ->groupBy('online_exam_assignment_id');

        // All counts refer to latest attempt per assignment. Revoked access is
        // counted separately, even when the participant previously completed.
        $count = DB::table('online_exam_assignments as a')
            ->where('a.online_exam_id', $exam->id)
            ->leftJoinSub($latestIds, 'latest', fn ($join) =>
                $join->on('latest.online_exam_assignment_id', '=', 'a.id'))
            ->leftJoin('online_exam_attempts as t', 't.id', '=', 'latest.latest_id')
            ->selectRaw(
                'COUNT(*) as total,
                COALESCE(SUM(CASE WHEN a.is_active = 0 THEN 1 ELSE 0 END),0) as revoked,
                COALESCE(SUM(CASE WHEN a.is_active = 1 AND t.id IS NULL THEN 1 ELSE 0 END),0) as not_started,
                COALESCE(SUM(CASE WHEN a.is_active = 1 AND t.status = ? AND t.expires_at > ? THEN 1 ELSE 0 END),0) as working,
                COALESCE(SUM(CASE WHEN a.is_active = 1 AND t.status = ? AND t.expires_at <= ? THEN 1 ELSE 0 END),0) as overdue,
                COALESCE(SUM(CASE WHEN a.is_active = 1 AND t.status = ? THEN 1 ELSE 0 END),0) as review,
                COALESCE(SUM(CASE WHEN a.is_active = 1 AND t.status IN (?,?,?) THEN 1 ELSE 0 END),0) as finished',
                [
                    'in_progress', $clock, 'in_progress', $clock,
                    'pending_review', 'submitted', 'graded', 'expired',
                ],
            )->first();

        $stats = [];
        foreach (['total', 'not_started', 'working', 'overdue', 'review', 'finished', 'revoked'] as $key) {
            $stats[$key] = (int) ($count->{$key} ?? 0);
        }

        $query = OnlineExamAssignment::query()
            ->where('online_exam_id', $exam->id)
            ->with([
                'user:id,name,email',
                'latestAttempt' => fn ($q) => $q
                    ->withCount([
                        'items as total_items_count',
                        'items as answered_items_count' => fn (Builder $items) => $items
                            ->whereNotNull('answered_at'),
                    ]),
            ]);

        $search = mb_substr(trim($search), 0, 100);
        if ($search !== '') {
            $query->whereHas('user', fn (Builder $user) => $user
                ->where(fn (Builder $q) => $q
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')));
        }

        $query = match ($status) {
            'revoked' => $query->where('is_active', false),
            'not_started' => $query->where('is_active', true)
                ->whereDoesntHave('latestAttempt'),
            'working' => $query->where('is_active', true)
                ->whereHas('latestAttempt', fn (Builder $q) => $q
                    ->where('status', 'in_progress')->where('expires_at', '>', $clock)),
            'overdue' => $query->where('is_active', true)
                ->whereHas('latestAttempt', fn (Builder $q) => $q
                    ->where('status', 'in_progress')->where('expires_at', '<=', $clock)),
            'review' => $query->where('is_active', true)
                ->whereHas('latestAttempt', fn (Builder $q) => $q->where('status', 'pending_review')),
            'finished' => $query->where('is_active', true)
                ->whereHas('latestAttempt', fn (Builder $q) => $q
                    ->whereIn('status', ['submitted', 'graded', 'expired'])),
            default => $query,
        };

        $paginator = $query->orderBy('id')->paginate(
            self::PAGE_SIZE, ['*'], 'monitorPage', max(1, $page),
        );

        $rows = $paginator->getCollection()->map(function (OnlineExamAssignment $assignment) use ($clock): array {
            $attempt = $assignment->latestAttempt;
            $status = match (true) {
                ! $assignment->is_active => 'revoked',
                $attempt === null => 'not_started',
                $attempt->status === 'in_progress' && $attempt->expires_at->lte($clock) => 'overdue',
                $attempt->status === 'in_progress' => 'working',
                $attempt->status === 'pending_review' => 'review',
                in_array($attempt->status, ['submitted', 'graded', 'expired'], true) => 'finished',
                default => 'unknown',
            };

            $seconds = $status === 'working'
                ? max(0, $attempt->expires_at->getTimestamp() - $clock->getTimestamp())
                : 0;

            return [
                'user_name' => (string) ($assignment->user?->name ?? 'Akun tidak ditemukan'),
                'email' => (string) ($assignment->user?->email ?? ''),
                'status' => $status,
                'attempt_number' => $attempt?->attempt_no,
                'answered' => $attempt?->answered_items_count ?? 0,
                'questions' => $attempt?->total_items_count ?? 0,
                'time_left' => $status === 'working'
                    ? sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
                    : '—',
                'started_at' => $attempt?->started_at?->format('d/m/Y H:i:s') ?? '—',
                'submitted_at' => $attempt?->submitted_at?->format('d/m/Y H:i:s') ?? '—',
            ];
        })->all();

        return [
            'exam' => $exam->title,
            'as_of' => $clock->format('d/m/Y H:i:s'),
            'stats' => $stats,
            'rows' => $rows,
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total_rows' => $paginator->total(),
        ];
    }
}
