<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\Document;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalReportService;
use App\Services\RegistrationWorkflowService;
use Carbon\Carbon;
use Database\Seeders\RegistrationOpeningSeeder;
use Database\Seeders\StudyProgramSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HigherEducationSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_tbu_with_seven_study_programs_and_openings(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(StudyProgramSeeder::class);
        $this->seed(RegistrationOpeningSeeder::class);

        $tbu = Unit::query()->where('code', 'TBU')->firstOrFail();

        $this->assertTrue($tbu->isHigherEducation());
        $this->assertSame(7, StudyProgram::query()->where('unit_id', $tbu->id)->count());
        $this->assertSame(7, RegistrationOpening::query()
            ->where('unit_id', $tbu->id)
            ->whereNotNull('study_program_id')
            ->where('status', 'open')
            ->count());

        $this->assertDatabaseHas('study_programs', [
            'unit_id' => $tbu->id,
            'code' => 'S1-IF',
            'name' => 'Informatika',
            'degree_level' => 'S1',
            'max_age' => 26,
        ]);
        $this->assertDatabaseHas('study_programs', [
            'unit_id' => $tbu->id,
            'code' => 'D3-PM',
            'name' => 'Penyaji Musik',
            'degree_level' => 'D3',
            'max_age' => null,
        ]);
    }

    public function test_study_programs_and_university_openings_enforce_institution_boundaries(): void
    {
        $school = Unit::create([
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);

        try {
            StudyProgram::create([
                'unit_id' => $school->id,
                'code' => 'INVALID',
                'name' => 'Bukan Program Studi',
                'degree_level' => 'S1',
                'is_active' => true,
            ]);
            $this->fail('School units must not accept study programs.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_id', $exception->errors());
        }

        $university = Unit::create([
            'name' => 'Universitas Uji',
            'code' => 'UNI',
            'institution_type' => 'university',
            'is_active' => true,
        ]);

        try {
            RegistrationOpening::create([
                'unit_id' => $university->id,
                'academic_year' => '2026/2027',
                'wave' => 'Gelombang 1',
                'registration_fee' => 350000,
                'status' => 'open',
            ]);
            $this->fail('University openings must require a study program.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('study_program_id', $exception->errors());
        }
    }

    public function test_s1_age_limit_is_enforced_by_study_program(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-03 10:00:00'));

        try {
            $unit = Unit::create([
                'name' => 'Taruna Bakti University',
                'code' => 'TBU',
                'institution_type' => 'university',
                'is_active' => true,
            ]);

            $program = StudyProgram::create([
                'unit_id' => $unit->id,
                'code' => 'S1-IF',
                'name' => 'Informatika',
                'degree_level' => 'S1',
                'max_age' => 26,
                'is_active' => true,
            ]);

            $program->assertApplicantAge('2000-09-03');
            $this->addToAssertionCount(1);

            try {
                $program->assertApplicantAge('1999-09-02');
                $this->fail('Applicants older than the configured maximum age must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('birth_date', $exception->errors());
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_document_completion_assigns_only_common_and_matching_program_tests(): void
    {
        [$registration, $programA, $programB, $staff] = $this->universityRegistrationFixture();

        $commonTest = AdmissionTest::create([
            'unit_id' => $registration->unit_id,
            'study_program_id' => null,
            'name' => 'Tes Umum',
            'code' => 'COMMON',
            'is_required' => true,
            'is_active' => true,
        ]);
        $matchingTest = AdmissionTest::create([
            'unit_id' => $registration->unit_id,
            'study_program_id' => $programA->id,
            'name' => 'Tes Informatika',
            'code' => 'IF',
            'is_required' => true,
            'is_active' => true,
        ]);
        $otherProgramTest = AdmissionTest::create([
            'unit_id' => $registration->unit_id,
            'study_program_id' => $programB->id,
            'name' => 'Tes Musik',
            'code' => 'MUSIC',
            'is_required' => true,
            'is_active' => true,
        ]);

        foreach (RegistrationWorkflowService::REQUIRED_DOCUMENTS as $type) {
            Document::create([
                'registration_id' => $registration->id,
                'type' => $type,
                'file_path' => 'documents/'.$registration->id.'/'.$type.'.pdf',
                'original_name' => $type.'.pdf',
                'file_type' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 1024,
                'sha256' => hash('sha256', $type),
                'malware_scan_status' => 'clean',
                'security_scanned_at' => now(),
                'is_verified' => true,
                'verified_at' => now(),
                'verified_by' => $staff->id,
            ]);
        }

        $this->assertTrue(app(RegistrationWorkflowService::class)->refreshDocumentStage($registration));

        $registration->refresh();
        $this->assertSame('tests', $registration->current_stage);
        $this->assertDatabaseHas('admission_test_results', [
            'registration_id' => $registration->id,
            'admission_test_id' => $commonTest->id,
        ]);
        $this->assertDatabaseHas('admission_test_results', [
            'registration_id' => $registration->id,
            'admission_test_id' => $matchingTest->id,
        ]);
        $this->assertDatabaseMissing('admission_test_results', [
            'registration_id' => $registration->id,
            'admission_test_id' => $otherProgramTest->id,
        ]);
        $this->assertSame(2, $registration->testResults()->count());
    }

    public function test_operational_report_can_filter_registrations_by_study_program(): void
    {
        [$registrationA, $programA, $programB] = $this->universityRegistrationFixture();

        $openingB = RegistrationOpening::create([
            'unit_id' => $registrationA->unit_id,
            'study_program_id' => $programB->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);

        $secondApplicant = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
            'email' => 'music@example.test',
        ]);

        Registration::create([
            'user_id' => $secondApplicant->id,
            'unit_id' => $registrationA->unit_id,
            'registration_opening_id' => $openingB->id,
            'registrant_type' => 'self',
            'registrant_relationship' => 'self',
            'nik' => '3273010101010002',
            'full_name' => 'Calon Mahasiswa Musik',
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2005-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'data_validation',
            'data_validation_status' => 'pending',
        ]);

        $staff = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $rows = app(OperationalReportService::class)->preview($staff, ['study_program_id' => $programA->id]);

        $this->assertCount(1, $rows);
        $this->assertSame($registrationA->id, $rows->first()->id);
        $this->assertSame($programA->id, $rows->first()->opening->study_program_id);
    }

    private function universityRegistrationFixture(): array
    {
        $unit = Unit::create([
            'name' => 'Taruna Bakti University',
            'code' => 'TBU',
            'institution_type' => 'university',
            'is_active' => true,
        ]);

        $programA = StudyProgram::create([
            'unit_id' => $unit->id,
            'code' => 'S1-IF',
            'name' => 'Informatika',
            'degree_level' => 'S1',
            'max_age' => 26,
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $programB = StudyProgram::create([
            'unit_id' => $unit->id,
            'code' => 'S1-SM',
            'name' => 'Seni Musik',
            'degree_level' => 'S1',
            'max_age' => 26,
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'study_program_id' => $programA->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 350000,
            'status' => 'open',
        ]);

        $applicant = User::factory()->create([
            'role' => 'user',
            'is_active' => true,
            'email' => 'informatics@example.test',
        ]);
        $staff = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'unit_id' => $unit->id,
        ]);

        $registration = Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registrant_type' => 'self',
            'registrant_relationship' => 'self',
            'nik' => '3273010101010001',
            'full_name' => 'Calon Mahasiswa Informatika',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2005-01-01',
            'home_address' => 'Bandung',
            'status' => 'documents_uploaded',
            'current_stage' => 'document_verification',
            'data_validation_status' => 'valid',
            'documents_completed_at' => now(),
        ]);

        return [$registration, $programA, $programB, $staff, $opening, $unit, $applicant];
    }
}
