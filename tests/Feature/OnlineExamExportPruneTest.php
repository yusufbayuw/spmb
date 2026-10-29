<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnlineExamExportPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_dry_run_then_bounded_cleanup_only_removes_old_uuid_named_cbt_reports(): void
    {
        $directory = 'exports/online-exam-results/';
        $oldA = $directory.Str::uuid().'.xlsx';
        $oldB = $directory.Str::uuid().'.xlsx';
        $recent = $directory.Str::uuid().'.xlsx';
        $unrelated = 'exports/online-exam-questions/'.Str::uuid().'.xlsx';
        $manual = $directory.'manual-exam-report.xlsx';

        foreach ([$oldA, $oldB, $recent, $unrelated, $manual] as $path) {
            Storage::disk('local')->put($path, 'synthetic-test');
        }

        foreach ([$oldA, $oldB, $unrelated, $manual] as $path) {
            touch(Storage::disk('local')->path($path), now()->subHours(48)->timestamp);
        }
        clearstatcache();

        $this->artisan('spmb:prune-online-exam-reports', ['--dry-run' => true, '--json' => true])
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists($oldA));
        $this->assertTrue(Storage::disk('local')->exists($oldB));

        $this->artisan('spmb:prune-online-exam-reports', ['--limit' => '1', '--json' => true])
            ->assertExitCode(0);

        $remainingOld = (int) Storage::disk('local')->exists($oldA)
            + (int) Storage::disk('local')->exists($oldB);
        $this->assertSame(1, $remainingOld, 'Bounded cleanup must delete at most one file.');

        $this->artisan('spmb:prune-online-exam-reports', ['--json' => true])
            ->assertExitCode(0);

        $this->assertFalse(Storage::disk('local')->exists($oldA));
        $this->assertFalse(Storage::disk('local')->exists($oldB));
        $this->assertTrue(Storage::disk('local')->exists($recent));
        $this->assertTrue(Storage::disk('local')->exists($manual));
        $this->assertTrue(Storage::disk('local')->exists($unrelated));
    }

    public function test_invalid_retention_or_limit_does_not_delete_anything(): void
    {
        $old = 'exports/online-exam-results/'.Str::uuid().'.xlsx';
        Storage::disk('local')->put($old, 'synthetic-test');
        touch(Storage::disk('local')->path($old), now()->subHours(50)->timestamp);
        clearstatcache();

        foreach ([
            ['--hours' => '1'], ['--hours' => '0'], ['--hours' => '8761'],
            ['--limit' => '0'], ['--limit' => '5001'], ['--limit' => 'not-a-number'],
        ] as $options) {
            $this->artisan('spmb:prune-online-exam-reports', $options)->assertExitCode(1);
            $this->assertTrue(Storage::disk('local')->exists($old));
        }
    }
}
