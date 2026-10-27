<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\OnlineExamResource;
use App\Filament\Admin\Resources\OnlineExamQuestionResource;
use App\Filament\Admin\Resources\OnlineExamItemResource;
use App\Filament\Admin\Resources\OnlineExamAssignmentResource;
use App\Filament\Admin\Resources\OnlineExamAttemptResource;
use App\Filament\Admin\Resources\OnlineExamResource\Pages\CreateOnlineExam;
use App\Filament\Admin\Resources\OnlineExamQuestionResource\Pages\CreateOnlineExamQuestion;
use App\Filament\Admin\Resources\OnlineExamItemResource\Pages\ListOnlineExamItems;
use App\Filament\Admin\Resources\OnlineExamAssignmentResource\Pages\ListOnlineExamAssignments;
use App\Filament\Admin\Resources\OnlineExamAttemptResource\Pages\ListOnlineExamAttempts;
use App\Filament\Applicant\Pages\OnlineExams;
use App\Filament\Applicant\Pages\OnlineExamTake;
use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\OnlineExamItem;
use App\Models\OnlineExamQuestion;
use App\Models\Unit;
use App\Models\User;
use App\Services\OnlineExamEngine;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnlineExamFilamentSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin_unit', 'super_admin', 'tu', 'pendaftar'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_staff_filament_pages_render_and_are_unit_scoped(): void
    {
        $unitA = Unit::create(['name' => 'Unit CBT A', 'code' => 'CBT-A', 'is_active' => true]);
        $unitB = Unit::create(['name' => 'Unit CBT B', 'code' => 'CBT-B', 'is_active' => true]);
        $a = OnlineExam::create(['unit_id' => $unitA->id, 'title' => 'Ujian Unit A']);
        OnlineExam::create(['unit_id' => $unitB->id, 'title' => 'Ujian Unit B']);
        $staff = User::factory()->create(['unit_id' => $unitA->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(OnlineExamResource::canViewAny());
        $this->assertSame([$a->id], OnlineExamResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame(0, OnlineExamQuestionResource::getEloquentQuery()->count());
        $this->assertSame(0, OnlineExamItemResource::getEloquentQuery()->count());
        $this->assertSame(0, OnlineExamAssignmentResource::getEloquentQuery()->count());
        $this->assertSame(0, OnlineExamAttemptResource::getEloquentQuery()->count());

        Livewire::test(CreateOnlineExam::class)->assertSuccessful();
        Livewire::test(CreateOnlineExamQuestion::class)->assertSuccessful();
        Livewire::test(ListOnlineExamItems::class)->assertSuccessful();
        Livewire::test(ListOnlineExamAssignments::class)->assertSuccessful();
        Livewire::test(ListOnlineExamAttempts::class)->assertSuccessful();
    }

    public function test_participant_sees_only_assigned_exam_and_never_sees_the_answer_key(): void
    {
        $unit = Unit::create(['name' => 'Unit CBT', 'code' => 'CBT-UI', 'is_active' => true]);
        $exam = OnlineExam::create([
            'unit_id' => $unit->id,
            'title' => 'Ujian Mandiri Tes Fisika',
            'shuffle_questions' => false, 'shuffle_options' => false,
        ]);
        $question = OnlineExamQuestion::create([
            'unit_id' => $unit->id, 'type' => 'single_choice',
            'prompt' => 'Contoh besaran vektor',
            'options' => ['choices' => ['alpha' => 'Gaya', 'beta' => 'Suhu']],
            'grading_config' => ['correct' => 'alpha'],
            'points' => 1, 'is_active' => true,
        ]);
        OnlineExamItem::create([
            'online_exam_id' => $exam->id,
            'online_exam_question_id' => $question->id,
        ]);
        $exam->update(['is_published' => true]);
        $participant = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $participant->assignRole('pendaftar');
        OnlineExamAssignment::create([
            'online_exam_id' => $exam->id,
            'user_id' => $participant->id,
            'is_active' => true,
        ]);

        $this->actingAs($participant);
        Filament::setCurrentPanel(Filament::getPanel('pendaftar'));

        Livewire::test(OnlineExams::class)
            ->assertSuccessful()
            ->assertSee('Ujian Mandiri Tes Fisika');

        $assignment = OnlineExamAssignment::query()->firstOrFail();
        $attempt = app(OnlineExamEngine::class)->start($assignment, $participant);

        Livewire::test(OnlineExamTake::class, ['attempt' => $attempt->uuid])
            ->assertSuccessful()
            ->assertSee('Contoh besaran vektor')
            ->assertSee('Gaya')
            ->assertDontSee('grading_snapshot')
            ->assertDontSee('"correct"');
    }
}
