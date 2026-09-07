<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\AdmissionTestResult;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\UnitConfiguration;
use App\Models\User;
use App\Services\AdmissionTestResultSpreadsheetService;
use App\Services\TestBookingService;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class AdmissionTestResultSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_export_contains_all_test_participants(): void
    {
        [$staff, $test, $first, $second] = $this->fixture();
        $path = sys_get_temp_dir().'/spmb-export-'.uniqid().'.xlsx';

        try {
            app(AdmissionTestResultSpreadsheetService::class)
                ->writeWorkbook($test, $staff, $path);

            $reader = new Reader();
            $reader->open($path);

            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getIndex() !== 0) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }

                break;
            }

            $reader->close();

            $this->assertSame([
                'RESULT_UUID',
                'TEST_UUID',
                'NO_REGISTRASI',
                'NAMA_PESERTA',
                'NILAI',
                'STATUS',
                'HASIL',
                'CATATAN',
            ], $rows[0]);
            $this->assertCount(3, $rows);
            $this->assertSame($first->registration_number, $rows[1][2]);
            $this->assertSame($second->registration_number, $rows[2][2]);
            $this->assertNotContains('REG-XLSX-0003', array_column($rows, 2));
        } finally {
            @unlink($path);
        }
    }

    public function test_xlsx_import_updates_entire_test_and_advances_each_registration(): void
    {
        [$staff, $test, $first, $second] = $this->fixture();
        $path = $this->writeImportWorkbook($test, [
            [$first->testResults()->firstOrFail(), 80, 'SELESAI', 'BELUM DINILAI', 'Nilai pertama'],
            [$second->testResults()->firstOrFail(), 60, 'SELESAI', 'BELUM DINILAI', 'Nilai kedua'],
        ]);

        try {
            $result = app(AdmissionTestResultSpreadsheetService::class)
                ->import($path, $staff);

            $this->assertSame(2, $result['updated']);
            $this->assertSame(0, $result['skipped']);

            $firstResult = $first->testResults()->firstOrFail();
            $secondResult = $second->testResults()->firstOrFail();

            $this->assertSame('completed', $firstResult->status);
            $this->assertSame('pass', $firstResult->result);
            $this->assertSame(80.0, (float) $firstResult->score);
            $this->assertSame($staff->id, $firstResult->assessed_by);

            $this->assertSame('completed', $secondResult->status);
            $this->assertSame('fail', $secondResult->result);
            $this->assertSame(60.0, (float) $secondResult->score);

            $this->assertSame('selection', $first->fresh()->current_stage);
            $this->assertSame('selection', $second->fresh()->current_stage);
            $this->assertSame(80.0, (float) $first->selection()->value('final_score'));
            $this->assertSame(60.0, (float) $second->selection()->value('final_score'));
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_xlsx_row_prevents_partial_result_updates(): void
    {
        [$staff, $test, $first, $second] = $this->fixture();

        $firstResult = $first->testResults()->firstOrFail();
        $secondResult = $second->testResults()->firstOrFail();

        $path = sys_get_temp_dir().'/spmb-import-invalid-'.uniqid().'.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers()));
        $writer->addRow(Row::fromValues([
            $firstResult->uuid,
            $test->uuid,
            $first->registration_number,
            $first->full_name,
            80,
            'SELESAI',
            'BELUM DINILAI',
            null,
        ]));
        $writer->addRow(Row::fromValues([
            $secondResult->uuid,
            $test->uuid,
            $second->registration_number,
            'NAMA DIUBAH',
            60,
            'SELESAI',
            'BELUM DINILAI',
            null,
        ]));
        $writer->close();

        try {
            app(AdmissionTestResultSpreadsheetService::class)->import($path, $staff);
            $this->fail('Import dengan identitas peserta yang berubah harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('identitas peserta telah berubah', implode(' ', $exception->errors()['file']));
        } finally {
            @unlink($path);
        }

        $this->assertSame('scheduled', $firstResult->fresh()->status);
        $this->assertSame('pending', $firstResult->fresh()->result);
        $this->assertSame('scheduled', $secondResult->fresh()->status);
        $this->assertSame('pending', $secondResult->fresh()->result);
    }

    private function fixture(): array
    {
        $this->seed(ShieldSeeder::class);

        $unit = Unit::create([
            'name' => 'Sekolah Dasar',
            'code' => 'SD-XLSX',
            'is_active' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'tu',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $staff->assignRole('tu');

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2026/2027',
            'wave' => 'Gelombang 1',
            'registration_fee' => 0,
            'status' => 'open',
        ]);

        $test = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes TKA',
            'code' => 'TKA',
            'sort_order' => 1,
            'is_required' => true,
            'is_active' => true,
            'passing_score' => 70,
            'result_type' => 'score',
        ]);

        $configuration = UnitConfiguration::create([
            'unit_id' => $unit->id,
            'version' => 1,
            'status' => 'published',
            'payment_enabled' => false,
            'documents_enabled' => false,
            'tests_enabled' => true,
            'selection_mode' => 'flexible',
            'post_announcement_enabled' => false,
            'fields' => [],
            'document_requirements' => [],
            'test_definitions' => [[
                'id' => $test->id,
                'name' => $test->name,
                'is_required' => true,
                'result_type' => 'score',
                'passing_score' => 70,
            ]],
            're_registration_requirements' => [],
            'published_at' => now(),
        ]);

        $first = $this->registration($opening, $configuration, 'REG-XLSX-0001', 'Peserta Pertama', '3273010101010201');
        $second = $this->registration($opening, $configuration, 'REG-XLSX-0002', 'Peserta Kedua', '3273010101010202');
        $third = $this->registration($opening, $configuration, 'REG-XLSX-0003', 'Peserta Belum Memilih Sesi', '3273010101010203');

        $session = TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'booking_closes_at' => now()->addDays(2),
            'location' => 'Ruang TKA',
            'capacity' => 2,
            'status' => 'active',
        ]);

        foreach ([$first, $second, $third] as $registration) {
            AdmissionTestResult::create([
                'registration_id' => $registration->id,
                'admission_test_id' => $test->id,
                'status' => 'unbooked',
                'result' => 'pending',
            ]);
        }

        foreach ([$first, $second] as $registration) {
            app(TestBookingService::class)->book(
                $registration,
                $session,
                $registration->user,
            );
        }

        $this->assertSame('unbooked', $third->testResults()->value('status'));

        return [$staff, $test, $first, $second, $third];
    }

    private function registration(
        RegistrationOpening $opening,
        UnitConfiguration $configuration,
        string $number,
        string $name,
        string $nik,
    ): Registration {
        return Registration::create([
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'unit_id' => $opening->unit_id,
            'unit_configuration_id' => $configuration->id,
            'registration_opening_id' => $opening->id,
            'registration_number' => $number,
            'registrant_type' => 'parent',
            'registrant_relationship' => 'father',
            'nik' => $nik,
            'full_name' => $name,
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2018-01-01',
            'home_address' => 'Bandung',
            'status' => 'submitted',
            'current_stage' => 'tests',
            'lifecycle_status' => 'active',
            'data_validation_status' => 'valid',
        ]);
    }

    /**
     * @param list<array{0:AdmissionTestResult,1:int|float,2:string,3:string,4:?string}> $data
     */
    private function writeImportWorkbook(AdmissionTest $test, array $data): string
    {
        $path = sys_get_temp_dir().'/spmb-import-'.uniqid().'.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers()));

        foreach ($data as [$result, $score, $status, $decision, $notes]) {
            $writer->addRow(Row::fromValues([
                $result->uuid,
                $test->uuid,
                $result->registration->registration_number,
                $result->registration->full_name,
                $score,
                $status,
                $decision,
                $notes,
            ]));
        }

        $writer->close();

        return $path;
    }

    /** @return list<string> */
    private function headers(): array
    {
        return [
            'RESULT_UUID',
            'TEST_UUID',
            'NO_REGISTRASI',
            'NAMA_PESERTA',
            'NILAI',
            'STATUS',
            'HASIL',
            'CATATAN',
        ];
    }
}
