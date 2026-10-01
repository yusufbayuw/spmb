<?php

namespace Tests\Feature;

use App\Models\CertificationProgram;
use App\Models\CertificationQuestion;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\CertificationService;
use App\Services\TrainingService;
use Database\Seeders\ShieldSeeder;
use Database\Seeders\TrainingCertificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingCertificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unit_completes_training_then_passes_certification_and_receives_verifiable_certificate(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin_unit');

        $training = TrainingProgram::query()->where('code', 'TRN-UNIT')->with('modules.lessons')->firstOrFail();

        foreach ($training->modules->flatMap->lessons as $lesson) {
            app(TrainingService::class)->completeLesson($user, $lesson);
        }

        $this->assertDatabaseHas('training_enrollments', [
            'training_program_id' => $training->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $program = CertificationProgram::query()->where('code', 'SCUA')->firstOrFail();

        $questionA = CertificationQuestion::create([
            'certification_program_id' => $program->id,
            'type' => 'single_choice',
            'question' => 'Konfigurasi yang digunakan pendaftar baru harus berada pada status apa?',
            'options' => ['A' => 'Draft', 'B' => 'Published'],
            'correct_answer' => 'B',
            'weight' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $questionB = CertificationQuestion::create([
            'certification_program_id' => $program->id,
            'type' => 'true_false',
            'question' => 'Data pendaftar boleh diekspor tanpa memperhatikan kewenangan role.',
            'correct_answer' => '0',
            'weight' => 1,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $service = app(CertificationService::class);
        $this->assertTrue($service->eligible($user, $program));

        $attempt = $service->start($user, $program);
        $result = $service->submit($attempt, $user, [
            $questionA->id => 'B',
            $questionB->id => '0',
        ]);

        $this->assertSame('passed', $result->status);
        $this->assertSame(100.0, (float) $result->score);
        $this->assertNotNull($result->certification);
        $this->assertTrue($user->fresh()->hasValidCertification('SCUA'));

        $this->get(route('certificates.verify', $result->certification))
            ->assertOk()
            ->assertSee($result->certification->certificate_number)
            ->assertSee('VALID');
    }

    public function test_certification_cannot_start_before_required_training_is_completed(): void
    {
        $this->seed(ShieldSeeder::class);
        $this->seed(TrainingCertificationSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('tu');

        $program = CertificationProgram::query()->where('code', 'SCAO')->firstOrFail();
        CertificationQuestion::create([
            'certification_program_id' => $program->id,
            'type' => 'true_false',
            'question' => 'Contoh soal.',
            'correct_answer' => '1',
            'weight' => 1,
            'is_active' => true,
        ]);

        $this->assertFalse(app(CertificationService::class)->eligible($user, $program));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CertificationService::class)->start($user, $program);
    }
}
