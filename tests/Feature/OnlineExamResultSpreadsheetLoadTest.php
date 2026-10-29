<?php

namespace Tests\Feature;

use App\Models\OnlineExam;
use App\Models\Unit;
use App\Models\User;
use App\Services\OnlineExamResultSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * An explicitly opted-in synthetic stress check. Never populate a real staging
 * or production database through this test.
 */
class OnlineExamResultSpreadsheetLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_streaming_xlsx_export_of_1200_accounts_and_2400_attempts(): void
    {
        if (getenv('SPMB_CBT_LOAD_TEST') !== '1'
            || ! app()->environment('testing')
            || DB::getDriverName() !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'spmb_test') {
            $this->markTestSkipped('Synthetic CBT export load test is restricted to isolated CI MySQL.');
        }

        Storage::fake('local');
        Role::findOrCreate('admin_unit', 'web');
        $unit = Unit::create(['name' => 'Synthetic CBT Load Unit', 'code' => 'LOAD-CBT', 'is_active' => true]);
        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $staff->assignRole('admin_unit');
        $exam = OnlineExam::create(['unit_id' => $unit->id, 'title' => 'Laporan Beban Sintetis']);

        $participants = 1200;
        $attemptsPerParticipant = 2;
        $now = now();
        $users = [];

        // Bulk fixtures deliberately bypass Eloquent and question generation
        // to benchmark the report writer/reader rather than test setup cost.
        for ($index = 1; $index <= $participants; $index++) {
            $users[] = [
                'uuid' => (string) Str::uuid(),
                'name' => 'Synthetic Learner '.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'email' => 'synthetic-cbt-'.$index.'@example.test',
                'password' => 'synthetic-fixture-not-for-login',
                'role' => 'user', 'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ];

            if (count($users) === 200 || $index === $participants) {
                DB::table('users')->insert($users);
                $users = [];
            }
        }

        $assignments = [];
        foreach (DB::table('users')->where('email', 'like', 'synthetic-cbt-%@example.test')
            ->orderBy('id')->pluck('id') as $userId) {
            $assignments[] = [
                'uuid' => (string) Str::uuid(),
                'online_exam_id' => $exam->id,
                'user_id' => $userId,
                'assigned_by' => $staff->id,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ];

            if (count($assignments) === 200) {
                DB::table('online_exam_assignments')->insert($assignments);
                $assignments = [];
            }
        }
        if ($assignments !== []) {
            DB::table('online_exam_assignments')->insert($assignments);
        }

        $attempts = [];
        foreach (DB::table('online_exam_assignments')->where('online_exam_id', $exam->id)
            ->orderBy('id')->get(['id', 'user_id']) as $assignment) {
            foreach (range(1, $attemptsPerParticipant) as $number) {
                $graded = $number === 1;
                $attempts[] = [
                    'uuid' => (string) Str::uuid(),
                    'online_exam_assignment_id' => $assignment->id,
                    'online_exam_id' => $exam->id,
                    'user_id' => $assignment->user_id,
                    'attempt_no' => $number,
                    'status' => $graded ? 'graded' : 'pending_review',
                    'duration_minutes_snapshot' => 60,
                    'passing_percentage_snapshot' => 60,
                    'earned_points' => $graded ? 2 : null,
                    'maximum_points' => $graded ? 2 : null,
                    'percentage' => $graded ? 100 : null,
                    'passed' => $graded ? true : null,
                    'started_at' => $now->copy()->subMinutes(15),
                    'expires_at' => $now->copy()->addMinutes(45),
                    'submitted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ];

                if (count($attempts) === 200) {
                    DB::table('online_exam_attempts')->insert($attempts);
                    $attempts = [];
                }
            }
        }
        if ($attempts !== []) {
            DB::table('online_exam_attempts')->insert($attempts);
        }

        $startedAt = hrtime(true);
        $result = app(OnlineExamResultSpreadsheetService::class)->generate($exam, $staff);
        $exportMilliseconds = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $memoryPeakBytes = memory_get_peak_usage(true);
        $fileBytes = filesize($result['path']);

        $this->assertSame($participants, $result['assignments']);
        $this->assertSame($participants * $attemptsPerParticipant, $result['attempts']);
        $this->assertLessThan(512 * 1024 * 1024, $memoryPeakBytes);
        $this->assertLessThan(50 * 1024 * 1024, $fileBytes);

        $reader = new Reader();
        $reader->open($result['path']);
        $rowCounts = [];
        $pendingScoreExposed = false;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetName = $sheet->getName();
                $rows = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    if ($rows > 0 && $sheetName === 'Riwayat Percobaan') {
                        $values = $row->toArray();
                        if (($values[6] ?? null) === 'pending_review'
                            && ($values[9] ?? null) !== null && ($values[9] ?? null) !== '') {
                            $pendingScoreExposed = true;
                        }
                    }
                    $rows++;
                }

                $rowCounts[$sheetName] = $rows;
            }
        } finally {
            $reader->close();
        }

        $this->assertSame($participants + 1, $rowCounts['Ringkasan Peserta']);
        $this->assertSame($participants * $attemptsPerParticipant + 1, $rowCounts['Riwayat Percobaan']);
        $this->assertFalse($pendingScoreExposed);
        $this->assertDatabaseCount('admission_test_results', 0);
        $this->assertDatabaseCount('online_exam_result_transfers', 0);

        if (getenv('SPMB_CBT_LOAD_REPORT_JSON') === '1') {
            file_put_contents(base_path('cbt-report-load-synthetic.json'), json_encode([
                'fixture' => 'synthetic_isolated_mysql',
                'package_count' => 1,
                'participants' => $participants,
                'attempts' => $participants * $attemptsPerParticipant,
                'export_duration_ms' => $exportMilliseconds,
                'php_peak_memory_bytes' => $memoryPeakBytes,
                'xlsx_file_bytes' => $fileBytes,
                'sheet_row_counts' => $rowCounts,
                'checks_passed' => true,
                'note' => 'Synthetic writer benchmark, not concurrent browser or real-staging UAT.',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }
}
