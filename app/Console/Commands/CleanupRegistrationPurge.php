<?php

namespace App\Console\Commands;

use App\Services\RegistrationPurgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupRegistrationPurge extends Command
{
    protected $signature = 'spmb:registration-purge:cleanup
        {manifest : Private manifest filename under purge-manifests/}
        {--execute : Attempt deleting remaining files after committed database purge}';

    protected $description = 'Preview/retry private file cleanup for a previously committed registration purge';

    public function handle(RegistrationPurgeService $purge): int
    {
        $manifest = (string) $this->argument('manifest');

        if (! preg_match('~^purge-manifests/[0-9a-f-]{36}\.json$~', $manifest)
            || ! Storage::disk('local')->exists($manifest)) {
            $this->components->error('Private purge manifest not found.');
            return self::FAILURE;
        }

        $data = json_decode((string) Storage::disk('local')->get($manifest), true);
        $this->info('Registration ID: '.(int) ($data['registration_id'] ?? 0));
        $this->info('Pending files: '.count($data['files'] ?? []));
        $this->info('Status: '.($data['status'] ?? 'unknown'));

        if (! $this->option('execute')) {
            $this->components->warn('PREVIEW ONLY. Add --execute to retry file cleanup after verifying the database deletion.');
            return self::SUCCESS;
        }

        try {
            if ($purge->cleanupFilesFromManifest($manifest)) {
                $this->components->warn('Some files remain. Repeat this command when storage is available.');
                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }

        $this->components->info('No pending files remain.');
        return self::SUCCESS;
    }
}
