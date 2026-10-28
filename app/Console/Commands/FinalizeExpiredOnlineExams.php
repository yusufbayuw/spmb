<?php

namespace App\Console\Commands;

use App\Models\OnlineExamAttempt;
use App\Services\OnlineExamEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class FinalizeExpiredOnlineExams extends Command
{
    protected $signature = 'spmb:finalize-online-exams
        {--limit=500 : Maximum number of expired attempts to process per run}';

    protected $description = 'Finalize expired online exam attempts without requiring a browser session';

    public function handle(OnlineExamEngine $engine): int
    {
        // The scheduled task may run during a rolling deployment before
        // the new additive migration has been applied.
        if (! Schema::hasTable('online_exam_attempts')) {
            $this->components->warn('Online exam tables have not been migrated.');

            return self::SUCCESS;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 5000) {
            $this->components->error('The --limit option must be an integer between 1 and 5000.');

            return self::FAILURE;
        }

        $ids = OnlineExamAttempt::query()
            ->where('status', 'in_progress')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $finalized = 0;

        foreach ($ids as $id) {
            if ($engine->finalizeExpired(OnlineExamAttempt::query()->findOrFail($id))) {
                $finalized++;
            }
        }

        $this->components->info("Finalized {$finalized} expired online exam attempts.");

        return self::SUCCESS;
    }
}
