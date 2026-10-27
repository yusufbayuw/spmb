<?php

namespace Tests\Feature;

use App\Models\AdmissionTestResult;
use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\OnlineExamAttemptItem;
use App\Models\OnlineExamItem;
use App\Models\OnlineExamQuestion;
use App\Models\TestBooking;
use App\Models\Unit;
use App\Models\User;
use App\Services\OnlineExamAccess;
use App\Services\OnlineExamEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OnlineExamPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin_unit', 'tu', 'pendaftar'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_standalone_exam_can_be_assigned_completed_and_graded_without_spmb_workflow(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario();
        $question = $this->addQuestion($exam, 'single_choice',
            ['choices' => ['A' => 'Gaya', 'B' => 'Suhu']],
            ['correct' => 'A']);
        $exam->update(['is_published' => true]);

        $assignment = app(OnlineExamEngine::class)->assign($exam, $user, $staff);
        $attempt = app(OnlineExamEngine::class)->start($assignment, $user);
        $item = $attempt->items->sole();

        app(OnlineExamEngine::class)->save($attempt, $user, $item->id, 'A');
        $done = app(OnlineExamEngine::class)->submit($attempt, $user);

        $this->assertSame('graded', $done->status);
        $this->assertEquals(100.0, (float) $done->percentage);
        $this->assertTrue($done->passed);
        $this->assertSame('graded', app(OnlineExamEngine::class)->submit($attempt, $user)->status);
        $this->assertSame(1, $assignment->attempts()->count());
        $this->assertSame(0, AdmissionTestResult::count());
        $this->assertSame(0, TestBooking::count());
        $this->assertSame(0, $user->registrations()->count());
    }

    public function test_all_phase_one_objective_evaluators_produce_correct_scores(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario(['passing_percentage' => 90]);
        $this->addQuestion($exam, 'multiple_choice',
            ['choices' => ['A' => 'Gaya', 'B' => 'Percepatan', 'C' => 'Suhu']],
            ['correct_options' => ['A', 'B']]);
        $this->addQuestion($exam, 'matching',
            ['prompts' => ['p1' => 'Bumi', 'p2' => 'Venus'],
                'choices' => ['a' => 'Planet Kehidupan', 'b' => 'Planet Terpanas']],
            ['pairs' => ['p1' => 'a', 'p2' => 'b']]);
        $this->addQuestion($exam, 'short_answer', [],
            ['accepted' => ['Newton', 'N'], 'case_sensitive' => false]);
        $this->addQuestion($exam, 'numerical', [],
            ['value' => 9.8, 'tolerance' => 0.2]);
        $this->addQuestion($exam, 'true_false', [], ['correct' => '1']);
        $exam->update(['is_published' => true, 'shuffle_questions' => false, 'shuffle_options' => false]);

        $assignment = app(OnlineExamEngine::class)->assign($exam, $user, $staff);
        $attempt = app(OnlineExamEngine::class)->start($assignment, $user);
        $items = $attempt->items->keyBy('type');

        $answers = [
            'multiple_choice' => ['B', 'A'],
            'matching' => ['p1' => 'a', 'p2' => 'b'],
            'short_answer' => 'newton',
            'numerical' => '9.95',
            'true_false' => '1',
        ];
        foreach ($answers as $type => $answer) {
            app(OnlineExamEngine::class)->save($attempt, $user, $items[$type]->id, $answer);
        }

        $done = app(OnlineExamEngine::class)->submit($attempt, $user);
        $this->assertSame('graded', $done->status);
        $this->assertEquals(100.0, (float) $done->percentage);
        $this->assertTrue($done->passed);
    }

    public function test_matching_has_partial_credit_and_multiple_choice_requires_exact_set(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario();
        $this->addQuestion($exam, 'matching',
            ['prompts' => ['p1' => 'Bumi', 'p2' => 'Venus'], 'choices' => ['a' => 'A', 'b' => 'B']],
            ['pairs' => ['p1' => 'a', 'p2' => 'b']]);
        $this->addQuestion($exam, 'multiple_choice',
            ['choices' => ['a' => 'A', 'b' => 'B', 'c' => 'C']],
            ['correct_options' => ['a', 'b']]);
        $exam->update(['is_published' => true]);

        $attempt = app(OnlineExamEngine::class)->start(app(OnlineExamEngine::class)->assign($exam, $user, $staff), $user);
        $items = $attempt->items->keyBy('type');
        app(OnlineExamEngine::class)->save($attempt, $user, $items['matching']->id, ['p1' => 'a', 'p2' => 'a']);
        app(OnlineExamEngine::class)->save($attempt, $user, $items['multiple_choice']->id, ['a']);

        $result = app(OnlineExamEngine::class)->submit($attempt, $user);
        $this->assertEquals(25.0, (float) $result->percentage);
        $this->assertFalse($result->passed);
    }

    public function test_essay_requires_authorized_review_and_is_graded_only_once(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario();
        $this->addQuestion($exam, 'essay', [], []);
        $exam->update(['is_published' => true]);

        $attempt = app(OnlineExamEngine::class)->start(app(OnlineExamEngine::class)->assign($exam, $user, $staff), $user);
        $item = $attempt->items->sole();
        app(OnlineExamEngine::class)->save($attempt, $user, $item->id, 'Jawaban uraian fisika.');
        $done = app(OnlineExamEngine::class)->submit($attempt, $user);
        $this->assertSame('pending_review', $done->status);
        $this->assertNull($done->percentage);

        $otherUnit = Unit::create(['name' => 'Unit Lain', 'code' => 'CBT-OTHER', 'is_active' => true]);
        $outsider = $this->staff($otherUnit);
        try {
            app(OnlineExamEngine::class)->reviewEssay($done, $outsider, [$item->id => 1]);
            $this->fail('Cross-unit review must be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $graded = app(OnlineExamEngine::class)->reviewEssay($done, $staff, [$item->id => 0.8]);
        $this->assertSame('graded', $graded->status);
        $this->assertEquals(80, (float) $graded->percentage);

        $this->expectException(ValidationException::class);
        app(OnlineExamEngine::class)->reviewEssay($graded, $staff, [$item->id => 1]);
    }

    public function test_snapshot_freezes_correct_answer_after_question_changes(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario(['max_attempts' => 2]);
        $question = $this->addQuestion($exam, 'single_choice',
            ['choices' => ['a' => 'A', 'b' => 'B']], ['correct' => 'a']);
        $exam->update(['is_published' => true]);

        $attempt = app(OnlineExamEngine::class)->start(app(OnlineExamEngine::class)->assign($exam, $user, $staff), $user);
        $item = $attempt->items->sole();
        $this->assertArrayNotHasKey('correct', $item->display_snapshot);
        $this->assertArrayNotHasKey('grading_snapshot', $item->toArray());
        $this->assertSame('a', $item->grading_snapshot['correct']);

        $question->update(['grading_config' => ['correct' => 'b']]);
        app(OnlineExamEngine::class)->save($attempt, $user, $item->id, 'a');
        $done = app(OnlineExamEngine::class)->submit($attempt, $user);
        $this->assertTrue($done->passed);

        $next = app(OnlineExamEngine::class)->start($attempt->assignment, $user);
        $this->assertSame('b', $next->items->sole()->grading_snapshot['correct']);
        $this->assertSame(2, $next->attempt_no);
    }

    public function test_other_participant_cannot_start_or_save_an_exam(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario();
        $this->addQuestion($exam, 'true_false', [], ['correct' => '1']);
        $exam->update(['is_published' => true]);

        $assignment = app(OnlineExamEngine::class)->assign($exam, $user, $staff);
        $outsider = $this->participant();
        try {
            app(OnlineExamEngine::class)->start($assignment, $outsider);
            $this->fail('Other participant should not start.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $attempt = app(OnlineExamEngine::class)->start($assignment, $user);
        $this->expectException(HttpException::class);
        app(OnlineExamEngine::class)->save($attempt, $outsider, $attempt->items->sole()->id, '1');
    }

    public function test_cross_unit_question_cannot_be_inserted_into_exam(): void
    {
        [$unit, $exam] = $this->scenario();
        $other = Unit::create(['name' => 'B', 'code' => 'CBT-B', 'is_active' => true]);
        $foreignQuestion = OnlineExamQuestion::create([
            'unit_id' => $other->id, 'type' => 'true_false',
            'prompt' => 'Tes', 'options' => [], 'grading_config' => ['correct' => '1'],
            'points' => 1, 'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        OnlineExamItem::create([
            'online_exam_id' => $exam->id,
            'online_exam_question_id' => $foreignQuestion->id,
        ]);
    }

    public function test_server_enforces_time_limit_and_never_reopens_attempt(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario(['duration_minutes' => 1]);
        $this->addQuestion($exam, 'true_false', [], ['correct' => '1']);
        $exam->update(['is_published' => true]);
        $assignment = app(OnlineExamEngine::class)->assign($exam, $user, $staff);
        $attempt = app(OnlineExamEngine::class)->start($assignment, $user);

        Carbon::setTestNow(now()->addMinutes(2));
        $done = app(OnlineExamEngine::class)->submit($attempt, $user);
        $this->assertSame('graded', $done->status);
        $this->assertFalse($done->passed);
        $this->expectException(ValidationException::class);
        app(OnlineExamEngine::class)->start($assignment, $user);
    }

    public function test_unpublished_exam_and_revoked_assignment_block_new_attempts(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario();
        $this->addQuestion($exam, 'true_false', [], ['correct' => '1']);
        $assignment = app(OnlineExamEngine::class)->assign($exam, $user, $staff);
        $this->assertFalse(app(OnlineExamAccess::class)->canSit($user, $assignment));

        $exam->update(['is_published' => true]);
        $assignment->update(['is_active' => false]);
        $this->assertFalse(app(OnlineExamAccess::class)->canSit($user, $assignment));
        $this->expectException(HttpException::class);
        app(OnlineExamEngine::class)->start($assignment, $user);
    }

    public function test_expired_attempt_is_finalized_even_when_attempt_limit_rejects_restart(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario(['duration_minutes' => 1]);
        $this->addQuestion($exam, 'true_false', [], ['correct' => '1']);
        $exam->update(['is_published' => true]);

        $assignment = app(OnlineExamEngine::class)->assign($exam, $user, $staff);
        $attempt = app(OnlineExamEngine::class)->start($assignment, $user);

        Carbon::setTestNow(now()->addMinutes(2));

        try {
            app(OnlineExamEngine::class)->start($assignment, $user);
            $this->fail('An attempt over the limit must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('exam', $exception->errors());
        }

        $this->assertSame('graded', $attempt->fresh()->status);
        $this->assertNotNull($attempt->fresh()->submitted_at);
        $this->assertEquals(0, (float) $attempt->fresh()->earned_points);
        $this->assertSame(1, $assignment->attempts()->count());
    }

    public function test_matching_blank_selection_is_saved_as_unanswered_and_other_pairs_are_scored(): void
    {
        [$unit, $exam, $staff, $user] = $this->scenario();
        $this->addQuestion($exam, 'matching',
            ['prompts' => ['p1' => 'Bumi', 'p2' => 'Venus'], 'choices' => ['a' => 'A', 'b' => 'B']],
            ['pairs' => ['p1' => 'a', 'p2' => 'b']]);
        $exam->update(['is_published' => true]);

        $assignment = app(OnlineExamEngine::class)->assign($exam, $user, $staff);
        $attempt = app(OnlineExamEngine::class)->start($assignment, $user);
        $item = $attempt->items->sole();

        app(OnlineExamEngine::class)->save($attempt, $user, $item->id, ['p1' => '', 'p2' => 'b']);

        $this->assertSame(['p2' => 'b'], $item->fresh()->answer['value']);
        $this->assertEquals(50, (float) app(OnlineExamEngine::class)
            ->submit($attempt, $user)->percentage);
    }

    public function test_numerical_question_rejects_non_finite_keys_and_tolerances(): void
    {
        [$unit] = $this->scenario();

        foreach ([
            ['value' => '1e309', 'tolerance' => 0],
            ['value' => 9.8, 'tolerance' => '1e309'],
        ] as $key) {
            try {
                OnlineExamQuestion::create([
                    'unit_id' => $unit->id, 'type' => 'numerical',
                    'prompt' => 'Percepatan gravitasi?', 'options' => [],
                    'grading_config' => $key, 'points' => 1, 'is_active' => true,
                ]);
                $this->fail('A non-finite numerical value must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('grading_config', $exception->errors());
            }
        }

        $this->assertSame(0, OnlineExamQuestion::query()->count());
    }

    /** @return array{Unit, OnlineExam, User, User} */
    private function scenario(array $examAttributes = []): array
    {
        $unit = Unit::create([
            'name' => 'Unit Ujian Mandiri', 'code' => 'CBT-A', 'is_active' => true,
        ]);
        $staff = $this->staff($unit);
        $user = $this->participant();

        $exam = OnlineExam::create(array_merge([
            'unit_id' => $unit->id, 'title' => 'Tes Online Fisika',
            'duration_minutes' => 60, 'max_attempts' => 1,
            'passing_percentage' => 60,
            'shuffle_questions' => false, 'shuffle_options' => false,
            'is_published' => false,
        ], $examAttributes));

        return [$unit, $exam, $staff, $user];
    }

    private function staff(Unit $unit): User
    {
        $staff = User::factory()->create([
            'unit_id' => $unit->id, 'role' => 'tu',
            'is_active' => true,
        ]);
        $staff->assignRole('admin_unit');

        return $staff;
    }

    private function participant(): User
    {
        $user = User::factory()->create([
            'role' => 'user', 'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('pendaftar');

        return $user;
    }

    private function addQuestion(OnlineExam $exam, string $type, array $options, array $key): OnlineExamQuestion
    {
        $question = OnlineExamQuestion::create([
            'unit_id' => $exam->unit_id,
            'type' => $type,
            'prompt' => 'Soal '.$type.'?',
            'options' => $options,
            'grading_config' => $key,
            'points' => 1,
            'is_active' => true,
        ]);
        OnlineExamItem::create([
            'online_exam_id' => $exam->id,
            'online_exam_question_id' => $question->id,
            'sort_order' => 1,
        ]);

        return $question;
    }
}
