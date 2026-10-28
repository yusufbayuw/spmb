<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\OnlineExamQuestionResource\Pages\ListOnlineExamQuestions;
use App\Models\OnlineExamCategory;
use App\Models\OnlineExamQuestion;
use App\Models\Unit;
use App\Models\User;
use App\Services\OnlineExamQuestionSpreadsheetService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OnlineExamQuestionSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Role::findOrCreate('admin_unit', 'web');
        Role::findOrCreate('super_admin', 'web');
    }

    public function test_template_imports_all_seven_question_types_and_preserves_keys(): void
    {
        $unit = $this->unit('XLSX-A');
        $actor = $this->staff($unit);
        $service = app(OnlineExamQuestionSpreadsheetService::class);
        $template = $service->generate($unit->id, $actor, true);

        $this->assertSame(OnlineExamQuestionSpreadsheetService::HEADERS, $this->rows($template['path'])[0]);

        $path = 'imports/online-exam-questions/import.xlsx';
        Storage::disk('local')->put($path, file_get_contents($template['path']));
        $result = $service->import($path, $unit->id, $actor);

        $this->assertSame(['imported' => 7, 'total' => 7], $result);
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertSame(7, OnlineExamQuestion::query()->where('unit_id', $unit->id)->count());
        $this->assertSame(7, OnlineExamQuestion::query()->where('unit_id', $unit->id)
            ->distinct()->count('type'));
        $this->assertSame('A', OnlineExamQuestion::query()->where('type', 'single_choice')
            ->firstOrFail()->grading_config['correct']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'online_exam.questions_imported']);
    }

    public function test_import_rejects_any_invalid_row_atomically(): void
    {
        $unit = $this->unit('XLSX-B');
        $actor = $this->staff($unit);
        $path = 'imports/online-exam-questions/bad.xlsx';

        $this->write($path, [
            ['true_false', 'Valid', '', 'easy', '', 1, 1, '{}', '{}', '{"correct":"1"}'],
            ['single_choice', 'Invalid', '', 'easy', '', 1, 1, '{"A":"Satu","B":"Dua"}', '{}', '{"correct":"Z"}'],
        ]);

        try {
            app(OnlineExamQuestionSpreadsheetService::class)->import($path, $unit->id, $actor);
            $this->fail('Invalid key must abort all questions.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertSame(0, OnlineExamQuestion::query()->count());
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_category_code_must_belong_to_same_active_unit(): void
    {
        $a = $this->unit('XLSX-C');
        $b = $this->unit('XLSX-D');
        OnlineExamCategory::create([
            'unit_id' => $b->id, 'code' => 'MAT', 'name' => 'Matematika', 'is_active' => true,
        ]);

        $path = 'imports/online-exam-questions/category.xlsx';
        $this->write($path, [
            ['true_false', 'Pertanyaan A', 'MAT', 'easy', '', 1, 1, '{}', '{}', '{"correct":"1"}'],
        ]);

        $this->expectException(ValidationException::class);
        try {
            app(OnlineExamQuestionSpreadsheetService::class)->import($path, $a->id, $this->staff($a));
        } finally {
            $this->assertSame(0, OnlineExamQuestion::query()->count());
        }
    }

    public function test_admin_unit_cannot_export_or_import_foreign_unit_question_keys(): void
    {
        $a = $this->unit('XLSX-E');
        $b = $this->unit('XLSX-F');
        $actor = $this->staff($a);
        $service = app(OnlineExamQuestionSpreadsheetService::class);

        try {
            $service->generate($b->id, $actor);
            $this->fail('Export of foreign unit must be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $path = 'imports/online-exam-questions/foreign.xlsx';
        $this->write($path, [
            ['true_false', 'Soal Lintas Unit', '', 'easy', '', 1, 1, '{}', '{}', '{"correct":"1"}'],
        ]);
        try {
            $service->import($path, $b->id, $actor);
            $this->fail('Import into foreign unit must be forbidden.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseMissing('online_exam_questions', ['prompt' => 'Soal Lintas Unit']);
    }

    public function test_export_only_includes_own_unit_and_keeps_keys_for_staff(): void
    {
        $a = $this->unit('XLSX-G');
        $b = $this->unit('XLSX-H');
        foreach ([[$a, 'A'], [$b, 'B']] as [$unit, $correct]) {
            OnlineExamQuestion::create([
                'unit_id' => $unit->id, 'type' => 'single_choice',
                'prompt' => 'Kunci Unit '.$unit->code,
                'options' => ['choices' => ['A' => 'Ya', 'B' => 'Tidak']],
                'grading_config' => ['correct' => $correct],
                'points' => 1, 'is_active' => true,
            ]);
        }

        $result = app(OnlineExamQuestionSpreadsheetService::class)
            ->generate($a->id, $this->staff($a));
        $rows = $this->rows($result['path']);

        $this->assertCount(2, $rows);
        $this->assertSame(OnlineExamQuestionSpreadsheetService::HEADERS, $rows[0]);
        $this->assertSame('Kunci Unit XLSX-G', $rows[1][1]);
        $this->assertSame(['correct' => 'A'], json_decode($rows[1][9], true));
        $this->assertStringNotContainsString('XLSX-H', implode('|', $rows[1]));
    }

    public function test_replay_same_file_is_rejected_without_creating_duplicates(): void
    {
        $unit = $this->unit('XLSX-I');
        $actor = $this->staff($unit);
        $service = app(OnlineExamQuestionSpreadsheetService::class);
        $data = [['true_false', 'Unik', '', 'easy', '', 1, 1, '{}', '{}', '{"correct":"1"}']];

        $this->write('imports/online-exam-questions/first.xlsx', $data);
        $service->import('imports/online-exam-questions/first.xlsx', $unit->id, $actor);

        $this->write('imports/online-exam-questions/replay.xlsx', $data);
        try {
            $service->import('imports/online-exam-questions/replay.xlsx', $unit->id, $actor);
            $this->fail('Repeated import must not create duplicate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertSame(1, OnlineExamQuestion::query()->count());
    }

    public function test_staff_question_list_renders_import_and_export_actions(): void
    {
        $unit = $this->unit('XLSX-J');
        $this->actingAs($this->staff($unit));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListOnlineExamQuestions::class)->assertSuccessful()
            ->assertActionExists('importQuestions')
            ->assertActionExists('exportQuestions')
            ->assertActionExists('downloadTemplate');
    }

    private function write(string $path, array $data): void
    {
        Storage::disk('local')->makeDirectory(dirname($path));
        $writer = new Writer();
        $writer->openToFile(Storage::disk('local')->path($path));
        $writer->addRow(Row::fromValues(OnlineExamQuestionSpreadsheetService::HEADERS));
        foreach ($data as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
    }

    private function rows(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);
        $result = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $result[] = $row->toArray();
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $result;
    }

    private function unit(string $code): Unit
    {
        return Unit::create(['name' => 'Unit '.$code, 'code' => $code, 'is_active' => true]);
    }

    private function staff(Unit $unit): User
    {
        $user = User::factory()->create([
            'unit_id' => $unit->id, 'role' => 'admin_unit', 'is_active' => true,
        ]);
        $user->assignRole('admin_unit');

        return $user;
    }
}
