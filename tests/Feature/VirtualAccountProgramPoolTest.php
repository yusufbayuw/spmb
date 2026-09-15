<?php

namespace Tests\Feature;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\VirtualAccountBatch;
use App\Services\RegistrationWorkflowService;
use App\Services\VirtualAccountImportService;
use App\Services\VirtualAccountTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VirtualAccountProgramPoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_program_code_is_required_normalized_and_unique_per_unit(): void
    {
        $unit = $this->university('TBU', 'Taruna Bakti University');
        $otherUnit = $this->university('TBU2', 'Taruna Bakti University 2');

        $program = StudyProgram::create([
            'unit_id' => $unit->id,
            'code' => 's1-if',
            'name' => 'Informatika',
            'degree_level' => 'S1',
            'is_active' => true,
        ]);

        $this->assertSame('S1-IF', $program->fresh()->code);

        StudyProgram::create([
            'unit_id' => $otherUnit->id,
            'code' => 's1-if',
            'name' => 'Informatika',
            'degree_level' => 'S1',
            'is_active' => true,
        ]);

        try {
            StudyProgram::create([
                'unit_id' => $unit->id,
                'code' => 'S1-IF',
                'name' => 'Informatika Duplikat',
                'degree_level' => 'S1',
                'is_active' => true,
            ]);
            $this->fail('Duplicate program code in the same unit must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }
    }

    public function test_unit_template_adds_program_column_and_reference_sheet_only_when_needed(): void
    {
        Storage::fake('local');

        $unit = $this->university('TBU', 'Taruna Bakti University');
        $program = $this->program($unit, 'S1-IF', 'Informatika');
        $staff = $this->staffFor($unit, 'admin_unit');

        $template = app(VirtualAccountTemplateService::class)->generate($staff);

        $this->assertSame(['va_number', 'bank', 'prodi'], $template['headers']);
        $sheets = $this->xlsxSheets($template['path']);
        $this->assertArrayHasKey('Referensi Prodi', $sheets);
        $this->assertContains([$program->code, $program->name], $sheets['Referensi Prodi']);

        $school = Unit::create([
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $schoolStaff = $this->staffFor($school, 'admin_unit');
        $schoolTemplate = app(VirtualAccountTemplateService::class)->generate($schoolStaff);

        $this->assertSame(['va_number', 'bank'], $schoolTemplate['headers']);
        $this->assertArrayNotHasKey('Referensi Prodi', $this->xlsxSheets($schoolTemplate['path']));
    }

    public function test_import_maps_program_code_and_blank_program_to_general_pool(): void
    {
        Storage::fake('local');

        $unit = $this->university('TBU', 'Taruna Bakti University');
        $program = $this->program($unit, 'S1-IF', 'Informatika');
        $staff = $this->staffFor($unit, 'admin_unit');
        $path = 'imports/virtual-accounts/program-pool.csv';

        Storage::disk('local')->put(
            $path,
            "va_number|bank|prodi\n100001|BNI|S1-IF\n100002|BNI|\n",
        );

        $result = app(VirtualAccountImportService::class)->importFile($path, $staff);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('virtual_accounts', [
            'va_number' => '100001',
            'unit_id' => $unit->id,
            'study_program_id' => $program->id,
        ]);
        $this->assertDatabaseHas('virtual_accounts', [
            'va_number' => '100002',
            'unit_id' => $unit->id,
            'study_program_id' => null,
        ]);
    }

    public function test_invalid_program_rejects_entire_import_atomically(): void
    {
        Storage::fake('local');

        $unit = $this->university('TBU', 'Taruna Bakti University');
        $this->program($unit, 'S1-IF', 'Informatika');
        $staff = $this->staffFor($unit, 'admin_unit');
        $path = 'imports/virtual-accounts/invalid-program.csv';

        Storage::disk('local')->put(
            $path,
            "va_number|bank|prodi\n100001|BNI|S1-IF\n100002|BNI|S1-TIDAK-ADA\n",
        );

        $result = app(VirtualAccountImportService::class)->importFile($path, $staff);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('S1-TIDAK-ADA', implode(' ', $result['errors']));
        $this->assertSame(0, VirtualAccount::count());
        $this->assertSame(0, VirtualAccountBatch::count());
    }

    public function test_duplicate_va_rejects_entire_import_atomically(): void
    {
        Storage::fake('local');

        $unit = Unit::create([
            'name' => 'SMA Taruna Bakti',
            'code' => 'SMA',
            'institution_type' => 'school',
            'is_active' => true,
        ]);
        $staff = $this->staffFor($unit, 'admin_unit');
        $path = 'imports/virtual-accounts/duplicate.csv';

        Storage::disk('local')->put(
            $path,
            "va_number|bank\n100001|BNI\n100001|BNI\n",
        );

        $result = app(VirtualAccountImportService::class)->importFile($path, $staff);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, VirtualAccount::count());
        $this->assertSame(0, VirtualAccountBatch::count());
    }

    public function test_assignment_prefers_program_pool_then_falls_back_to_general_pool(): void
    {
        Queue::fake();

        $unit = $this->university('TBU', 'Taruna Bakti University');
        $program = $this->program($unit, 'S1-IF', 'Informatika');
        $opening = $this->opening($unit, $program, 'Gelombang 1');
        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);

        $general = VirtualAccount::create([
            'unit_id' => $unit->id,
            'study_program_id' => null,
            'bank' => 'BNI',
            'va_number' => '200001',
            'status' => 'available',
        ]);
        $specific = VirtualAccount::create([
            'unit_id' => $unit->id,
            'study_program_id' => $program->id,
            'bank' => 'BNI',
            'va_number' => '200002',
            'status' => 'available',
        ]);

        $first = $this->registration($unit, $opening, '3273010101011001', 'Pendaftar Satu');
        $second = $this->registration($unit, $opening, '3273010101011002', 'Pendaftar Dua');
        $workflow = app(RegistrationWorkflowService::class);

        $firstPayment = $workflow->assignAvailableVirtualAccount($first, $staff);
        $secondPayment = $workflow->assignAvailableVirtualAccount($second, $staff);

        $this->assertSame($specific->id, $firstPayment?->virtual_account_id);
        $this->assertSame($general->id, $secondPayment?->virtual_account_id);
    }

    public function test_program_specific_va_is_never_borrowed_by_another_program(): void
    {
        Queue::fake();

        $unit = $this->university('TBU', 'Taruna Bakti University');
        $informatics = $this->program($unit, 'S1-IF', 'Informatika');
        $management = $this->program($unit, 'S1-MNJ', 'Manajemen');
        $informaticsOpening = $this->opening($unit, $informatics, 'Gelombang IF');
        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);

        VirtualAccount::create([
            'unit_id' => $unit->id,
            'study_program_id' => $management->id,
            'bank' => 'BNI',
            'va_number' => '300001',
            'status' => 'available',
        ]);

        $registration = $this->registration($unit, $informaticsOpening, '3273010101012001', 'Pendaftar IF');
        $payment = app(RegistrationWorkflowService::class)->assignAvailableVirtualAccount($registration, $staff);

        $this->assertNull($payment);
        $this->assertSame('available', VirtualAccount::query()->firstOrFail()->status);
        $this->assertSame('virtual_account', $registration->fresh()->current_stage);
    }

    public function test_waiting_assignment_skips_empty_program_and_continues_other_programs(): void
    {
        Queue::fake();

        $unit = $this->university('TBU', 'Taruna Bakti University');
        $informatics = $this->program($unit, 'S1-IF', 'Informatika');
        $management = $this->program($unit, 'S1-MNJ', 'Manajemen');
        $informaticsOpening = $this->opening($unit, $informatics, 'Gelombang IF');
        $managementOpening = $this->opening($unit, $management, 'Gelombang MNJ');
        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);

        $informaticsRegistration = $this->registration($unit, $informaticsOpening, '3273010101013001', 'Pendaftar IF');
        $managementRegistration = $this->registration($unit, $managementOpening, '3273010101013002', 'Pendaftar MNJ');

        VirtualAccount::create([
            'unit_id' => $unit->id,
            'study_program_id' => $management->id,
            'bank' => 'BNI',
            'va_number' => '400001',
            'status' => 'available',
        ]);

        $assigned = app(RegistrationWorkflowService::class)->assignWaitingRegistrationsForUnit($unit, $staff);

        $this->assertSame(1, $assigned);
        $this->assertSame('virtual_account', $informaticsRegistration->fresh()->current_stage);
        $this->assertSame('payment', $managementRegistration->fresh()->current_stage);
        $this->assertSame('400001', $managementRegistration->payments()->firstOrFail()->va_number);
    }

    private function university(string $code, string $name): Unit
    {
        return Unit::create([
            'name' => $name,
            'code' => $code,
            'institution_type' => 'university',
            'is_active' => true,
        ]);
    }

    private function program(Unit $unit, string $code, string $name): StudyProgram
    {
        return StudyProgram::create([
            'unit_id' => $unit->id,
            'code' => $code,
            'name' => $name,
            'degree_level' => 'S1',
            'is_active' => true,
        ]);
    }

    private function staffFor(Unit $unit, string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $staff = User::factory()->create([
            'unit_id' => $unit->id,
            'role' => $roleName,
            'is_active' => true,
        ]);
        $staff->assignRole($role);

        return $staff;
    }

    private function opening(Unit $unit, StudyProgram $program, string $wave): RegistrationOpening
    {
        return RegistrationOpening::create([
            'unit_id' => $unit->id,
            'study_program_id' => $program->id,
            'academic_year' => '2026/2027',
            'wave' => $wave,
            'registration_fee' => 350000,
            'status' => 'open',
        ]);
    }

    private function registration(Unit $unit, RegistrationOpening $opening, string $nik, string $name): Registration
    {
        $applicant = User::factory()->create(['role' => 'user', 'is_active' => true]);

        return Registration::create([
            'user_id' => $applicant->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => $nik,
            'full_name' => $name,
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2008-01-01',
            'home_address' => 'Bandung',
            'status' => 'verified',
            'current_stage' => 'virtual_account',
            'lifecycle_status' => 'active',
            'data_validation_status' => 'valid',
        ]);
    }

    /** @return array<string, list<array<int, string>>> */
    private function xlsxSheets(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);
        $sheets = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($cell): string => (string) $cell->getValue(), $row->getCells());
                }
                $sheets[$sheet->getName()] = $rows;
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }
}
