<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PruneOnlineExamResultExports extends Command
{
    protected $signature = 'spmb:prune-online-exam-reports
        {--hours=24 : Only remove files older than this many hours (24-8760)}
        {--limit=500 : Maximum number of matching files to remove (1-5000)}
        {--dry-run : List counts without deleting any files}
        {--json : Output machine-readable counts only, never filenames}';

    protected $description = 'Remove abandoned private CBT XLSX report files after a safe retention period';

    public function handle(): int
    {
        $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($hours === false || $hours < 24 || $hours > 8760
            || $limit === false || $limit < 1 || $limit > 5000) {
            $this->components->error('Use --hours=24..8760 and --limit=1..5000.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $directory = 'exports/online-exam-results';
        $cutoff = now()->subHours($hours)->timestamp;
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $eligible = 0;
        $deleted = 0;

        try {
            // Files() is not recursive. Never operate outside this exact
            // directory or delete arbitrary XLSX/uploads/partial other files.
            foreach ($disk->files($directory) as $path) {
                $checked++;
                $filename = basename($path);

                if ($path !== $directory.'/'.$filename
                    || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx'
                    || ! Str::isUuid(pathinfo($filename, PATHINFO_FILENAME))
                    || $disk->lastModified($path) >= $cutoff) {
                    continue;
                }

                $eligible++;
                if (! $dryRun && ! $disk->delete($path)) {
                    throw new \RuntimeException('Private report cleanup failed.');
                }

                if (! $dryRun) {
                    $deleted++;
                }

                if ($eligible >= $limit) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('CBT report cleanup failed. Check private storage permissions.');

            return self::FAILURE;
        }

        $result = [
            'status' => 'ok',
            'dry_run' => $dryRun,
            'checked' => $checked,
            'eligible' => $eligible,
            'deleted' => $deleted,
            'limit' => $limit,
            'retention_hours' => $hours,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->components->info(
                'Private CBT report cleanup: '.$deleted.' deleted, '.$eligible.' eligible, '
                .$checked.' checked'.($dryRun ? ' (dry run).' : '.'),
            );
        }

        return self::SUCCESS;
    }
}
