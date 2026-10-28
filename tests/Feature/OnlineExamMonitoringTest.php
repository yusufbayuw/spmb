<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\OnlineExamAttemptResource\Pages\MonitorOnlineExams;
use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\OnlineExamAttempt;
use App\Models\OnlineExamItem;
use App\Models\OnlineExamQuestion;
use App\Models\Unit;
use App\Models\User;
use App\Services\OnlineExamEngine;
use App\Services\OnlineExamMonitoringService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OnlineExamMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin_unit', 'pendaftar'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_dashboard_classifies_unstarted_active_expired_finished_and_revoked_participants(): void
    {
        $unit = $this->unit('MON-A');
        $exam = $this->exam($unit);
        $staff = $this->staff($unit);

        $unstarted = $this->assign($exam, $this->applicant(), $staff);
        $working = $this->assign($exam, $this->applicant(), $staff);
        $overdue = $this->assign($exam, $this->applicant(), $staff);
        $finished = $this->assign($exam, $this->applicant(), $staff);
        $revoked = $this->assign($exam, $this->applicant(), $staff);

        $activeAttempt = app(OnlineExamEngine::class)->start($working, $working->user);
        $dueAttempt = app(OnlineExamEngine::class)->start($overdue, $overdue->user);
        $dueAttempt->update(['expires_at' => now()->subSecond()]);
        $doneAttempt = app(OnlineExamEngine::class)->start($finished, $finished->user);
        app(OnlineExamEngine::class)->submit($doneAttempt, $finished->user);
        $revoked->update(['is_active' => false]);

        $before = $activeAttempt->fresh()->updated_at;
        $snapshot = app(OnlineExamMonitoringService::class)->snapshot($exam, $staff);
        $this->assertSame([
            'total' => 5,
            'not_started' => 1,
            'working' => 1,
            'overdue' => 1,
            'review' => 0,
            'finished' => 1,
            'revoked' => 1,
        ], $snapshot['stats']);
        $this->assertSame(5, count($snapshot['rows']));
        $this->assertSame($before->toDateTimeString(), $activeAttempt->fresh()->updated_at->toDateTimeString());
        $this->assertSame('in_progress', $dueAttempt->fresh()->status);

        $rows = collect($snapshot['rows'])->keyBy('email');
        $this->assertSame('working', $rows[$working->user->email]['status']);
        $this->assertSame('overdue', $rows[$overdue->user->email]['status']);
        $this->assertSame('revoked', $rows[$revoked->user->email]['status']);
        $this->assertSame('finished', $rows[$finished->user->email]['status']);
        $this->assertSame('not_started', $rows[$unstarted->user->email]['status']);
        $this->assertMatchesRegularExpression('/^\d\d:\d\d:\d\d$/', $rows[$working->user->email]['time_left']);
        $this->assertSame('—', $rows[$overdue->user->email]['time_left']);

        app(OnlineExamEngine::class)->finalizeExpired($dueAttempt);
        $afterScheduler = app(OnlineExamMonitoringService::class)->snapshot($exam, $staff);
        $this->assertSame(0, $afterScheduler['stats']['overdue']);
        $this->assertSame(2, $afterScheduler['stats']['finished']);
    }

    public function test_dashboard_progress_shows_answered_count_and_never_exposes_keys(): void
    {
        $unit = $this->unit('MON-B');
        $exam = $this->exam($unit);
        $staff = $this->staff($unit);
        $assignment = $this->assign($exam, $this->applicant(), $staff);
        $attempt = app(OnlineExamEngine::class)->start($assignment, $assignment->user);
        $item = $attempt->items->firstOrFail();
        app(OnlineExamEngine::class)->save($attempt, $assignment->user, $item->id, '1');

        $snapshot = app(OnlineExamMonitoringService::class)->snapshot($exam, $staff);
        $row = $snapshot['rows'][0];
        $this->assertSame(1, $row['answered']);
        $this->assertSame(1, $row['questions']);
        $this->assertArrayNotHasKey('grading_snapshot', $row);
        $this->assertStringNotContainsString('grading_config', json_encode($snapshot));
        $this->assertStringNotContainsString('correct', json_encode($snapshot));
    }

    public function test_monitoring_only_returns_authorized_units(): void
    {
        $unitA = $this->unit('MON-C');
        $unitB = $this->unit('MON-D');
        $examA = $this->exam($unitA);
        $examB = $this->exam($unitB);
        $staffA = $this->staff($unitA);
        $this->assign($examA, $this->applicant(), $staffA);
        $this->assign($examB, $this->applicant(), $this->staff($unitB));

        $service = app(OnlineExamMonitoringService::class);
        $this->assertSame(1, $service->snapshot($examA, $staffA)['stats']['total']);
        try {
            $service->snapshot($examB, $staffA);
            $this->fail('Access to foreign unit monitor must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(1, $service->snapshot($examB, $this->superAdmin())['stats']['total']);
        try {
            $service->snapshot($examA, $this->applicant());
            $this->fail('Applicant should not see monitoring data.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_filters_and_pagination_are_unit_scoped_and_recalculate_stats(): void
    {
        $unit = $this->unit('MON-E');
        $exam = $this->exam($unit);
        $staff = $this->staff($unit);
        foreach (range(1, 30) as $i) {
            $this->assign($exam, $this->applicant(), $staff);
        }

        $service = app(OnlineExamMonitoringService::class);
        $first = $service->snapshot($exam, $staff, 1);
        $second = $service->snapshot($exam, $staff, 2);

        $this->assertSame(30, $first['stats']['total']);
        $this->assertSame(25, count($first['rows']));
        $this->assertSame(5, count($second['rows']));
        $this->assertSame(2, $second['last_page']);
        $this->assertSame(30, $first['total_rows']);

        $this->assertSame(30, $service->snapshot($exam, $staff, 1, '', 'not_started')['total_rows']);
        $this->assertSame(0, $service->snapshot($exam, $staff, 1, '', 'working')['total_rows']);
        $this->assertSame(0, $service->snapshot($exam, $staff, 1, 'unfindable@example.test')['total_rows']);
    }

    public function test_latest_attempt_wins_over_historical_completed_attempt(): void
    {
        $unit = $this->unit('MON-F');
        $exam = $this->exam($unit);
        $staff = $this->staff($unit);
        $assignment = $this->assign($exam, $this->applicant(), $staff);
        $finished = app(OnlineExamEngine::class)->start($assignment, $assignment->user);
        app(OnlineExamEngine::class)->submit($finished, $assignment->user);

        OnlineExamAttempt::query()->create([
            'online_exam_assignment_id' => $assignment->id,
            'online_exam_id' => $exam->id,
            'user_id' => $assignment->user_id,
            'attempt_no' => 2,
            'status' => 'pending_review',
            'duration_minutes_snapshot' => 60,
            'passing_percentage_snapshot' => 60,
            'started_at' => now(),
            'expires_at' => now()->addHour(),
            'submitted_at' => now(),
        ]);

        $snapshot = app(OnlineExamMonitoringService::class)->snapshot($exam, $staff);
        $this->assertSame(0, $snapshot['stats']['finished']);
        $this->assertSame(1, $snapshot['stats']['review']);
        $this->assertSame(2, $snapshot['rows'][0]['attempt_number']);
    }

    public function test_filament_monitor_renders_and_rechecks_foreign_unit_selection(): void
    {
        $unitA = $this->unit('MON-G');
        $unitB = $this->unit('MON-H');
        $examA = $this->exam($unitA);
        $examB = $this->exam($unitB);
        $this->actingAs($this->staff($unitA));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(MonitorOnlineExams::class)
            ->assertSuccessful()
            ->assertSee($examA->title)
            ->assertDontSee($examB->title)
            ->set('examId', $examA->id)
            ->call('refresh')
            ->assertSee('Belum Mulai')
            ->assertDontSee('grading_snapshot');
    }

    private function unit(string $code): Unit
    {
        return Unit::query()->create(['name' => 'Unit '.$code, 'code' => $code, 'is_active' => true]);
    }

    private function exam(Unit $unit): OnlineExam
    {
        $exam = OnlineExam::query()->create(['unit_id' => $unit->id, 'title' => 'Monitoring '.$unit->code]);
        $question = OnlineExamQuestion::query()->create([
            'unit_id' => $unit->id, 'type' => 'true_false',
            'prompt' => 'Kuis monitoring '.$unit->code,
            'options' => [], 'grading_config' => ['correct' => '1'],
            'points' => 1, 'is_active' => true,
        ]);
        OnlineExamItem::query()->create([
            'online_exam_id' => $exam->id, 'online_exam_question_id' => $question->id,
        ]);
        $exam->update(['is_published' => true]);

        return $exam;
    }

    private function staff(Unit $unit): User
    {
        $user = User::factory()->create(['unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true]);
        $user->assignRole('admin_unit');

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function applicant(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $user->assignRole('pendaftar');

        return $user;
    }

    private function assign(OnlineExam $exam, User $user, User $staff): OnlineExamAssignment
    {
        return app(OnlineExamEngine::class)->assign($exam, $user, $staff);
    }
}
