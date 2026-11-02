<?php

namespace Tests\Feature;

use App\Filament\Applicant\Pages\OnlineExams;
use App\Models\OnlineExam;
use App\Models\OnlineExamAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicantOnlineExamNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('pendaftar', 'web');
    }

    public function test_menu_is_hidden_without_assignments_and_for_other_users_assignments(): void
    {
        $unit = Unit::create(['name' => 'Unit Menu CBT', 'code' => 'CBT-NAV', 'is_active' => true]);
        $applicant = $this->applicant();
        $anotherApplicant = $this->applicant();
        $this->actingAs($applicant);

        $this->assertFalse(OnlineExams::shouldRegisterNavigation());

        $exam = OnlineExam::create(['unit_id' => $unit->id, 'title' => 'Tes Khusus']);
        OnlineExamAssignment::create([
            'online_exam_id' => $exam->id, 'user_id' => $anotherApplicant->id,
            'is_active' => true,
        ]);

        $this->assertFalse(OnlineExams::shouldRegisterNavigation());
    }

    public function test_menu_follows_existing_assignment_visibility_without_changing_exam_access(): void
    {
        $unit = Unit::create(['name' => 'Unit Menu CBT', 'code' => 'CBT-NAV', 'is_active' => true]);
        $applicant = $this->applicant();
        $this->actingAs($applicant);

        $exam = OnlineExam::create(['unit_id' => $unit->id, 'title' => 'Tes Tersedia']);
        $assignment = OnlineExamAssignment::create([
            'online_exam_id' => $exam->id, 'user_id' => $applicant->id,
            'is_active' => true,
        ]);

        $this->assertTrue(OnlineExams::shouldRegisterNavigation());

        $assignment->update(['is_active' => false]);
        $this->assertFalse(OnlineExams::shouldRegisterNavigation());

        $assignment->update(['is_active' => true]);
        $this->assertTrue(OnlineExams::shouldRegisterNavigation());

        $unit->update(['is_active' => false]);
        $this->assertFalse(OnlineExams::shouldRegisterNavigation());
    }

    private function applicant(): User
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $user->assignRole('pendaftar');

        return $user;
    }
}
