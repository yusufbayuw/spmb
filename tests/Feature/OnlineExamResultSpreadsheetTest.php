<?php

namespace Tests\Feature;

use App\Models\AdmissionTestResult;
use App\Models\OnlineExam;
use App\Models\OnlineExamItem;
use App\Models\OnlineExamQuestion;
use App\Models\OnlineExamResultTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Services\OnlineExamEngine;
use App\Services\OnlineExamResultSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OnlineExamResultSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        foreach (['super_admin', 'admin_unit', 'pendaftar'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_export_includes_every_assignment_and_attempt_without_answer_keys_or_spmb_mutations(): void
    {
        [$unit, $exam, $staff] = $this->scenario('RPT-A');
        $participant = $this->applicant(['name' => '=2+3']);
        $engine = app(OnlineExamEngine::class);
        $assigned = $engine->assign($exam, $participant, $staff);

        $first = $engine->start($assigned, $participant);
        $engine->save($first, $participant, $first->items->sole()->id, 'A');
        $done = $engine->submit($first, $participant);
        $this->assertSame('graded', $done->status);
        $this->assertEquals(100, (float) $done->percentage);

        $second = $engine->start($assigned, $participant);
        $this->assertSame('in_progress', $second->status);

        $revoked = $engine->assign($exam, $this->applicant(), $staff);
        $revoked->update(['is_active' => false]);
        $idle = $engine->assign($exam, $this->applicant(), $staff);

        $result = app(OnlineExamResultSpreadsheetService::class)->generate($exam, $staff);
        $this->assertSame(3, $result['assignments']);
        $this->assertSame(2, $result['attempts']);
        $sheets = $this->sheets($result['path']);
        $this->assertCount(3, $sheets);
        $this->assertSame(OnlineExamResultSpreadsheetService::SUMMARY_HEADERS, $sheets['Ringkasan Peserta'][0]);
        $this->assertSame(OnlineExamResultSpreadsheetService::ATTEMPT_HEADERS, $sheets['Riwayat Percobaan'][0]);
        $this->assertCount(4, $sheets['Ringkasan Peserta']);
        $this->assertCount(3, $sheets['Riwayat Percobaan']);

        $summary = collect(array_slice($sheets['Ringkasan Peserta'], 1))
            ->keyBy(fn (array $row): string => $row[0]);
        $this->assertSame("'=2+3", $summary[$assigned->uuid][1]);
        $this->assertSame('in_progress', $summary[$assigned->uuid][5]);
        $this->assertSame(2, (int) $summary[$assigned->uuid][4]);
        $this->assertEmpty($summary[$assigned->uuid][7]); // Not the old graded score.
        $this->assertSame('Dicabut', $summary[$revoked->uuid][3]);
        $this->assertSame('belum_mulai', $summary[$revoked->uuid][5]);
        $this->assertSame('belum_mulai', $summary[$idle->uuid][5]);

        $history = collect(array_slice($sheets['Riwayat Percobaan'], 1))
            ->keyBy(fn (array $row): string => $row[0]);
        $this->assertSame('graded', $history[$done->uuid][6]);
        $this->assertEquals(100, (float) $history[$done->uuid][9]);
        $this->assertSame('Lulus', $history[$done->uuid][10]);
        $this->assertSame('in_progress', $history[$second->uuid][6]);
        $this->assertEmpty($history[$second->uuid][9]);

        $contents = json_encode($sheets, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('TOP_SECRET_QUESTION_182', $contents);
        $this->assertStringNotContainsString('TOP_SECRET_OPTION_987', $contents);
        $this->assertStringNotContainsString('grading_snapshot', $contents);
        $this->assertSame(0, AdmissionTestResult::query()->count());
        $this->assertSame(0, OnlineExamResultTransfer::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'online_exam.results_exported',
            'unit_id' => $unit->id,
            'user_id' => $staff->id,
        ]);
    }

    public function test_only_authorized_staff_can_export_and_other_unit_data_never_leaks(): void
    {
        [$unitA, $examA, $staffA] = $this->scenario('RPT-B');
        [$unitB, $examB, $staffB] = $this->scenario('RPT-C');
        app(OnlineExamEngine::class)->assign($examA, $this->applicant(['name' => 'Peserta A']), $staffA);
        app(OnlineExamEngine::class)->assign($examB, $this->applicant(['name' => 'FOREIGN_PERSON_982']), $staffB);

        $service = app(OnlineExamResultSpreadsheetService::class);
        foreach ([$staffB, $this->applicant()] as $unauthorized) {
            try {
                $service->generate($examA, $unauthorized);
                $this->fail('Cross-unit or applicant export must be forbidden.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $report = $service->generate($examA, $staffA);
        $this->assertSame(1, $report['assignments']);
        $this->assertStringNotContainsString(
            'FOREIGN_PERSON_982', json_encode($this->sheets($report['path'])),
        );

        $superAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $superAdmin->assignRole('super_admin');
        $this->assertSame(1, $service->generate($examB, $superAdmin)['assignments']);
    }

    public function test_empty_report_download_uses_private_headers_and_leaves_workflow_untouched(): void
    {
        [$unit, $exam, $staff] = $this->scenario('RPT-D');
        $service = app(OnlineExamResultSpreadsheetService::class);

        $response = $service->download($exam, $staff);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $sheets = $this->sheets($response->getFile()->getPathname());
        $this->assertCount(1, $sheets['Ringkasan Peserta']);
        $this->assertCount(1, $sheets['Riwayat Percobaan']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'online_exam.results_exported']);
        $this->assertDatabaseCount('online_exam_assignments', 0);
        $this->assertDatabaseCount('admission_test_results', 0);
    }

    /** @return array{Unit, OnlineExam, User} */
    private function scenario(string $code): array
    {
        $unit = Unit::create(['name' => 'Unit '.$code, 'code' => $code, 'is_active' => true]);
        $staff = User::factory()->create([
            'unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true,
        ]);
        $staff->assignRole('admin_unit');

        $exam = OnlineExam::create([
            'unit_id' => $unit->id, 'title' => 'Laporan Tes '.$code,
            'max_attempts' => 2, 'shuffle_questions' => false,
            'shuffle_options' => false,
        ]);
        $question = OnlineExamQuestion::create([
            'unit_id' => $unit->id, 'type' => 'single_choice',
            'prompt' => 'TOP_SECRET_QUESTION_182',
            'options' => [
                'choices' => [
                    'A' => 'TOP_SECRET_OPTION_987',
                    'B' => 'Jawaban lain',
                ],
            ],
            'grading_config' => ['correct' => 'A'],
            'points' => 1, 'is_active' => true,
        ]);
        OnlineExamItem::create([
            'online_exam_id' => $exam->id, 'online_exam_question_id' => $question->id,
        ]);
        $exam->update(['is_published' => true]);

        return [$unit, $exam, $staff];
    }

    private function applicant(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'user', 'is_active' => true], $attributes));
        $user->assignRole('pendaftar');

        return $user;
    }

    /** @return array<string, list<list<mixed>>> */
    private function sheets(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);
        $sheets = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                $sheets[$sheet->getName()] = $rows;
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }
}
