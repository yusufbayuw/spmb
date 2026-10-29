<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\OnlineExamResource\Pages\ListOnlineExams;
use App\Models\OnlineExam;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnlineExamReportUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::findOrCreate('admin_unit', 'web');
    }

    public function test_staff_can_download_private_xlsx_from_filament_without_creating_spmb_records(): void
    {
        $unit = Unit::create(['name' => 'UAT CBT A', 'code' => 'UAT-CBT-A', 'is_active' => true]);
        $foreign = Unit::create(['name' => 'UAT CBT B', 'code' => 'UAT-CBT-B', 'is_active' => true]);

        $exam = OnlineExam::create(['unit_id' => $unit->id, 'title' => 'Simulasi CBT Mandiri']);
        $otherExam = OnlineExam::create(['unit_id' => $foreign->id, 'title' => 'RAHASIA CBT UNIT B']);

        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListOnlineExams::class)
            ->assertSuccessful()
            ->assertSee($exam->title)
            ->assertDontSee($otherExam->title)
            ->callTableAction('exportResultXlsx', $exam)
            ->assertFileDownloaded('hasil-cbt-simulasi-cbt-mandiri-'.$exam->id.'.xlsx');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'online_exam.results_exported',
            'user_id' => $staff->id,
            'unit_id' => $unit->id,
        ]);
        $this->assertDatabaseCount('online_exam_assignments', 0);
        $this->assertDatabaseCount('admission_test_results', 0);
        $this->assertDatabaseCount('online_exam_result_transfers', 0);
    }
}
