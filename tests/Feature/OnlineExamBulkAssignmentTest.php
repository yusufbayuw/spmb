<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\OnlineExamAssignmentResource\Pages\BulkAssignOnlineExamAssignments;
use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\OnlineExamItem;
use App\Models\OnlineExamQuestion;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\RegistrationPathway;
use App\Models\Unit;
use App\Models\User;
use App\Services\OnlineExamBulkAssignmentService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OnlineExamBulkAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin_unit', 'pendaftar'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_preview_and_execute_only_include_active_submitted_registrations_in_selected_opening_and_pathway(): void
    {
        $unit = $this->unit('BULK-A');
        $opening = $this->opening($unit, 'Gelombang 1');
        $nextWave = $this->opening($unit, 'Gelombang 2');
        $regular = $this->pathway($unit, 'Reguler');
        $special = $this->pathway($unit, 'Prestasi');
        $exam = $this->exam($unit);
        $staff = $this->staff($unit);
        $correct = $this->applicant();
        $wrongPath = $this->applicant();
        $wrongWave = $this->applicant();
        $notSubmitted = $this->applicant();
        $withdrawn = $this->applicant();
        $inactive = $this->applicant(['is_active' => false]);

        $this->registration($correct, $unit, $opening, $regular, true, 'active');
        $this->registration($wrongPath, $unit, $opening, $special, true, 'active');
        $this->registration($wrongWave, $unit, $nextWave, $regular, true, 'active');
        $this->registration($notSubmitted, $unit, $opening, $regular, false, 'active');
        $this->registration($withdrawn, $unit, $opening, $regular, true, 'withdrawn');
        $this->registration($inactive, $unit, $opening, $regular, true, 'active');

        $service = app(OnlineExamBulkAssignmentService::class);
        $preview = $service->preview($exam, $opening->id, $regular->id, $staff);

        $this->assertSame(1, $preview['eligible']);
        $this->assertSame(1, $preview['new']);
        $this->assertSame(0, OnlineExamAssignment::query()->count());

        $result = $service->execute($exam, $opening->id, $regular->id, $preview['signature'], $staff);
        $this->assertSame(['created' => 1, 'already_assigned' => 0, 'revoked' => 0], $result);
        $this->assertSame([$correct->id], OnlineExamAssignment::query()->pluck('user_id')->all());
        $this->assertDatabaseHas('audit_logs', ['event' => 'online_exam.bulk_assigned']);
    }

    public function test_duplicate_and_revoked_assignments_are_not_overwritten_or_reactivated(): void
    {
        $unit = $this->unit('BULK-B');
        $opening = $this->opening($unit);
        $exam = $this->exam($unit);
        $staff = $this->staff($unit);
        $active = $this->applicant();
        $revoked = $this->applicant();
        $fresh = $this->applicant();

        foreach ([$active, $revoked, $fresh] as $user) {
            $this->registration($user, $unit, $opening);
        }

        OnlineExamAssignment::create([
            'online_exam_id' => $exam->id, 'user_id' => $active->id,
            'assigned_by' => $staff->id, 'is_active' => true,
        ]);
        OnlineExamAssignment::create([
            'online_exam_id' => $exam->id, 'user_id' => $revoked->id,
            'assigned_by' => $staff->id, 'is_active' => false,
        ]);

        $service = app(OnlineExamBulkAssignmentService::class);
        $preview = $service->preview($exam, $opening->id, null, $staff);
        $this->assertSame(3, $preview['eligible']);
        $this->assertSame(1, $preview['new']);
        $this->assertSame(1, $preview['already_assigned']);
        $this->assertSame(1, $preview['revoked']);

        $this->assertSame(
            ['created' => 1, 'already_assigned' => 1, 'revoked' => 1],
            $service->execute($exam, $opening->id, null, $preview['signature'], $staff),
        );
        $this->assertFalse(OnlineExamAssignment::query()
            ->where('user_id', $revoked->id)->firstOrFail()->is_active);
        $this->assertSame(3, OnlineExamAssignment::query()->count());

        $repeat = $service->preview($exam, $opening->id, null, $staff);
        $this->assertSame(0, $repeat['new']);
        $this->assertSame(
            ['created' => 0, 'already_assigned' => 2, 'revoked' => 1],
            $service->execute($exam, $opening->id, null, $repeat['signature'], $staff),
        );
        $this->assertSame(3, OnlineExamAssignment::query()->count());
    }

    public function test_stale_preview_rejects_changed_cohort_without_partial_assignments(): void
    {
        $unit = $this->unit('BULK-C');
        $opening = $this->opening($unit);
        $exam = $this->exam($unit);
        $staff = $this->staff($unit);
        $this->registration($this->applicant(), $unit, $opening);

        $service = app(OnlineExamBulkAssignmentService::class);
        $preview = $service->preview($exam, $opening->id, null, $staff);
        $this->registration($this->applicant(), $unit, $opening);

        try {
            $service->execute($exam, $opening->id, null, $preview['signature'], $staff);
            $this->fail('Outdated preview must never execute.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preview', $exception->errors());
        }

        $this->assertSame(0, OnlineExamAssignment::query()->count());
    }

    public function test_cross_unit_and_unpublished_exam_are_rejected(): void
    {
        $a = $this->unit('BULK-D');
        $b = $this->unit('BULK-E');
        $examA = $this->exam($a);
        $examB = $this->exam($b);
        $openingA = $this->opening($a);
        $openingB = $this->opening($b);
        $staffA = $this->staff($a);
        $service = app(OnlineExamBulkAssignmentService::class);

        try {
            $service->preview($examB, $openingB->id, null, $staffA);
            $this->fail('A foreign unit exam must be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            $service->preview($examA, $openingB->id, null, $staffA);
            $this->fail('A foreign opening must be forbidden.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('opening', $exception->errors());
        }

        $examA->update(['is_published' => false]);
        $this->expectException(ValidationException::class);
        $service->preview($examA, $openingA->id, null, $staffA);
    }

    public function test_admin_ui_previews_then_assigns_with_explicit_confirmation(): void
    {
        $unit = $this->unit('BULK-F');
        $opening = $this->opening($unit);
        $exam = $this->exam($unit);
        $staff = $this->staff($unit);
        $this->registration($this->applicant(), $unit, $opening);

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(BulkAssignOnlineExamAssignments::class)
            ->assertSuccessful()
            ->set('examId', $exam->id)
            ->set('openingId', $opening->id)
            ->call('preview')
            ->assertSet('previewData.new', 1)
            ->call('assign')
            ->assertHasNoErrors()
            ->assertSet('previewData', []);

        $this->assertSame(1, OnlineExamAssignment::query()->count());
    }

    private function unit(string $code): Unit
    {
        return Unit::create(['code' => $code, 'name' => $code, 'is_active' => true]);
    }

    private function opening(Unit $unit, string $wave = 'Gelombang 1'): RegistrationOpening
    {
        return RegistrationOpening::create([
            'unit_id' => $unit->id, 'academic_year' => '2027/2028',
            'wave' => $wave, 'status' => 'open',
        ]);
    }

    private function pathway(Unit $unit, string $name): RegistrationPathway
    {
        return RegistrationPathway::create([
            'unit_id' => $unit->id, 'name' => $name, 'is_active' => true,
        ]);
    }

    private function exam(Unit $unit): OnlineExam
    {
        $exam = OnlineExam::create(['unit_id' => $unit->id, 'title' => 'Tes '.$unit->code]);
        $q = OnlineExamQuestion::create([
            'unit_id' => $unit->id, 'type' => 'true_false', 'prompt' => 'Uji coba?',
            'options' => [], 'grading_config' => ['correct' => '1'],
            'points' => 1, 'is_active' => true,
        ]);
        OnlineExamItem::create([
            'online_exam_id' => $exam->id, 'online_exam_question_id' => $q->id,
        ]);
        $exam->update(['is_published' => true]);

        return $exam;
    }

    private function staff(Unit $unit): User
    {
        $user = User::factory()->create([
            'unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true,
        ]);
        $user->assignRole('admin_unit');

        return $user;
    }

    private function applicant(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['role' => 'user', 'is_active' => true]);
        $user->assignRole('pendaftar');

        return $user;
    }

    private function registration(
        User $user,
        Unit $unit,
        RegistrationOpening $opening,
        ?RegistrationPathway $pathway = null,
        bool $submitted = true,
        string $lifecycle = 'active',
    ): Registration {
        return Registration::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_pathway_id' => $pathway?->id,
            'registration_number' => 'R-'.$user->id.'-'.$opening->id,
            'full_name' => $user->name,
            'nik' => str_pad((string) $user->id, 16, '0', STR_PAD_LEFT),
            'gender' => 'L', 'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung', 'phone' => '081234567890',
            'email' => $user->email,
            'previous_school' => 'Sekolah Asal',
            'current_stage' => 'tests',
            'lifecycle_status' => $lifecycle,
            'submitted_at' => $submitted ? now() : null,
        ]);
    }
}
