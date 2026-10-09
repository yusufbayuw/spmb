<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only deployment diagnostics. This command must not mutate the database,
 * storage, session, queue, cache, secrets, or external services.
 */
class ReleasePreflight extends Command
{
    protected $signature = 'spmb:release:preflight
        {--profile=auto : auto, development, staging, or production}
        {--json : Emit machine-readable results without displaying secrets}';

    protected $description = 'Read-only release checks for SPMB configuration and database migrations';

    public function handle(): int
    {
        $requested = (string) $this->option('profile');
        $profile = $requested === 'auto'
            ? (in_array((string) config('app.env'), ['production', 'staging'], true)
                ? (string) config('app.env')
                : 'development')
            : $requested;

        if (! in_array($profile, ['development', 'staging', 'production'], true)) {
            $this->components->error('Invalid profile. Use auto, development, staging, or production.');

            return self::FAILURE;
        }

        $live = $profile !== 'development';
        $production = $profile === 'production';
        $checks = [];

        $add = static function (string $id, bool $passing, string $explanation, bool $blocking = true) use (&$checks): void {
            $checks[] = [
                'id' => $id,
                'status' => $passing ? 'pass' : ($blocking ? 'fail' : 'warning'),
                'message' => $explanation,
            ];
        };

        $add('environment', ! $live || config('app.env') === $profile,
            'APP_ENV matches the selected deployment profile.', $live);
        $add('application_key', filled(config('app.key')), 'Application encryption key is configured.');
        $add('debug', ! $live || config('app.debug') === false,
            'Debug mode is disabled on staging/production.', $live);
        $add('https', ! $live || str_starts_with((string) config('app.url'), 'https://'),
            'APP_URL uses HTTPS on staging/production.', $live);

        $private = config('filesystems.disks.applicant-private', []);
        $public = config('filesystems.disks.public', []);
        $privateRoot = rtrim((string) ($private['root'] ?? ''), '/\\');
        $publicRoot = rtrim((string) ($public['root'] ?? ''), '/\\');

        $privateSafe = ($private['driver'] ?? null) === 'local'
            && $privateRoot !== ''
            && $publicRoot !== ''
            && $privateRoot !== $publicRoot
            && ! str_starts_with($privateRoot.'/', $publicRoot.'/')
            && ($private['serve'] ?? false) === false
            && ($private['visibility'] ?? 'private') === 'private';

        $add('applicant_storage', $privateSafe,
            'Applicant-private storage is separate from the public disk and cannot be served directly.');

        $add('session', ! $live || (filled(config('session.driver')) && ! in_array(config('session.driver'), ['array', 'null'], true)
            && config('session.secure') === true),
            'Persistent sessions and secure cookies are configured.', $live);
        $add('queue', ! $live || (filled(config('queue.default')) && ! in_array(config('queue.default'), ['sync', 'null'], true)),
            'Background jobs use a persistent queue connection.', $live);
        $add('mail', ! $live || (filled(config('mail.default')) && ! in_array(config('mail.default'), ['log', 'array'], true)
            && ! in_array(config('mail.from.address'), [null, '', 'hello@example.com'], true)),
            'A delivery-capable mailer and non-placeholder sender are configured.', $live);
        $add('reset_locked', ! $production || blank(config('spmb.reset.allowed_target')),
            'The development reset allowlist is not enabled in production.', $production);
        $add('malware_scanning', ! $live || config('spmb.uploads.require_malware_scan') === true,
            'Malware scanning is required for uploads.', false);

        $dbConnected = false;
        try {
            DB::connection()->select('SELECT 1');
            $dbConnected = true;
        } catch (Throwable) {
            // Do not print the connection string or database exception.
        }

        $add('database', $dbConnected, 'Database responds to a read-only query.');

        $pendingCount = null;
        if ($dbConnected) {
            try {
                if (Schema::hasTable('migrations')) {
                    $ran = DB::table('migrations')->pluck('migration')->all();
                    $paths = glob(database_path('migrations/*.php')) ?: [];
                    $available = array_map(
                        static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
                        $paths,
                    );
                    $pendingCount = count(array_diff($available, $ran));
                }
            } catch (Throwable) {
                $pendingCount = null;
            }
        }

        $add('migrations', $pendingCount === 0,
            $pendingCount === null
                ? 'Migration state could not be verified.'
                : "Pending migrations: {$pendingCount}.");

        $failed = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'fail'));
        $warnings = count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'warning'));

        $manual = [
            'Restore a database and private-file backup on an isolated environment.',
            'Verify queue workers and Laravel scheduler are running and supervised.',
            'Send a real password-recovery email through the configured mail service.',
            'Perform cross-unit authorization, applicant journey, and manual-transfer-proof UAT.',
            'Verify file upload, download, and signed document behavior over HTTPS.',
            'Confirm staging isolation, approved release SHA, rollback owner, and go/no-go sign-off.',
        ];

        if ($this->option('json')) {
            $this->line(json_encode([
                'profile' => $profile,
                'status' => $failed === 0 ? 'checks_passed' : 'checks_failed',
                'failures' => $failed,
                'warnings' => $warnings,
                'checks' => $checks,
                'manual_validation_required' => $manual,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        } else {
            $this->line('SPMB release preflight: '.$profile);
            $this->table(
                ['Check', 'Result', 'Description'],
                array_map(static fn (array $check): array => [
                    $check['id'], strtoupper($check['status']), $check['message'],
                ], $checks),
            );
            $this->line("Failures: {$failed}; warnings: {$warnings}.");
            $this->components->warn('Passing preflight never substitutes for staging UAT, backup-restore drills, or release approval.');
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
