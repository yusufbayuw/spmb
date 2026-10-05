<?php

namespace Tests\Feature;

use App\Models\AdmissionTest;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\TestBooking;
use App\Models\TestSession;
use App\Models\Unit;
use App\Models\User;
use App\Services\TestParticipantExcelExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TestParticipantExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unit_can_export_only_participants_from_its_test_session(): void
    {
        $unit = Unit::create([
            'name' => 'SMP Export Tes',
            'code' => 'SMP-EXP',
            'is_active' => true,
        ]);

        $actor = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $actor->assignRole('admin_unit');

        $opening = RegistrationOpening::create([
            'unit_id' => $unit->id,
            'academic_year' => '2027/2028',
            'wave' => 'Gelombang 1',
            'status' => 'open',
        ]);

        $test = AdmissionTest::create([
            'unit_id' => $unit->id,
            'name' => 'Tes Akademik',
            'code' => 'AKAD-EXP',
            'is_required' => true,
            'is_active' => true,
        ]);

        $session = TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'booking_closes_at' => now()->addDays(2),
            'location' => 'Lab Komputer',
            'capacity' => 20,
            'status' => 'active',
        ]);

        $parent = User::factory()->create(['is_active' => true]);

        $registration = Registration::create([
            'user_id' => $parent->id,
            'unit_id' => $unit->id,
            'registration_opening_id' => $opening->id,
            'registration_number' => 'SMP-0005',
            'full_name' => 'Mulyono',
            'nik' => '3273010101010005',
            'gender' => 'L',
            'birth_place' => 'Bandung',
            'birth_date' => '2010-01-01',
            'home_address' => 'Bandung',
            'phone' => '081234567890',
            'email' => 'mulyono@example.test',
            'previous_school' => 'SD Contoh',
            'current_stage' => 'tests',
        ]);

        TestBooking::create([
            'registration_id' => $registration->id,
            'admission_test_id' => $test->id,
            'test_session_id' => $session->id,
            'revision' => 1,
        ]);

        $result = app(TestParticipantExcelExportService::class)->export($session, $actor);

        $this->assertSame(1, $result['count']);
        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.xlsx', $result['filename']);

        $sheets = $this->readWorkbook($result['path']);

        $this->assertSame(['Peserta Tes', 'Info Sesi'], array_keys($sheets));
        $this->assertContains('No. Pendaftaran', $sheets['Peserta Tes'][0]);
        $this->assertContains('Nama Peserta', $sheets['Peserta Tes'][0]);
        $this->assertContains('SMP-0005', $sheets['Peserta Tes'][1]);
        $this->assertContains('Mulyono', $sheets['Peserta Tes'][1]);
        $this->assertContains('Lab Komputer', $sheets['Peserta Tes'][1]);

        $participantCountRow = collect($sheets['Info Sesi'])
            ->first(fn (array $row): bool => ($row[0] ?? null) === 'Jumlah peserta');

        $this->assertIsArray($participantCountRow);
        $this->assertContains(1, $participantCountRow);

        @unlink($result['path']);
    }

    public function test_admin_unit_cannot_export_another_units_test_session(): void
    {
        $firstUnit = Unit::create([
            'name' => 'Unit Pertama',
            'code' => 'UNIT-A',
            'is_active' => true,
        ]);
        $secondUnit = Unit::create([
            'name' => 'Unit Kedua',
            'code' => 'UNIT-B',
            'is_active' => true,
        ]);

        $actor = User::factory()->create([
            'role' => 'admin_unit',
            'unit_id' => $firstUnit->id,
            'is_active' => true,
        ]);
        $actor->assignRole('admin_unit');

        $test = AdmissionTest::create([
            'unit_id' => $secondUnit->id,
            'name' => 'Tes Unit Lain',
            'is_required' => true,
            'is_active' => true,
        ]);

        $session = TestSession::create([
            'admission_test_id' => $test->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'booking_closes_at' => now()->addDays(2),
            'location' => 'Ruang B',
            'capacity' => 20,
            'status' => 'active',
        ]);

        try {
            app(TestParticipantExcelExportService::class)->export($session, $actor);
            $this->fail('Admin unit dapat mengekspor sesi milik unit lain.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function readWorkbook(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);

        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();

        return $sheets;
    }
}
