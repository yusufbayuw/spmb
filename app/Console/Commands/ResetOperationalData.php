<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ApplicantFileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResetOperationalData extends Command
{
    protected $signature = 'spmb:reset-operational
        {--execute : Delete development operational data after all preflight checks}
        {--target= : Fingerprint shown in the preview and explicitly allowed in configuration}
        {--backup-confirmed : Operator confirms both database and private files have tested backups}
        {--cleanup-manifest= : Resume private-file cleanup from a previously committed reset}';

    protected $description = 'Development-only, configuration-preserving operational reset with independent target approval';

    /**
     * Child-first explicit allowlist. Never delete configuration tables, test
     * definitions/sessions, unit data, roles, bank VA pool or audit history.
     */
    private const OPERATIONAL_TABLES = [
        'continuation_registration_links',
        'registration_consents',
        'registration_achievements',
        'registration_academic_scores',
        're_registration_items',
        'admission_offers',
        'announcements',
        'admission_test_results',
        'test_bookings',
        'selections',
        'payment_receipts',
        'payments',
        'documents',
        'parent_infos',
        'registrations',
        'continuation_candidates',
        'registration_number_sequences',
    ];

    private const PROTECTED_TABLES = [
        'units', 'education_levels', 'study_programs', 'registration_pathways',
        'registration_openings', 'unit_configurations', 'admission_tests',
        'test_sessions', 'selection_batches', 'admission_quotas',
        'virtual_accounts', 'virtual_account_batches', 'app_settings',
        'account_consent_policies', 'faqs', 'roles', 'permissions',
        'role_has_permissions', 'training_programs', 'training_modules',
        'training_lessons', 'training_module_assessments',
        'training_module_questions', 'certification_programs',
        'certification_questions', 'practical_scenarios',
        'practical_scenario_records', 'practical_scenario_actions',
        'practical_assertions', 'provinces', 'regencies', 'districts', 'villages',
    ];

    private const FILE_PREFIXES = [
        'documents/', 'payments/', 're-registration/', 'pre-registration/',
    ];

    public function handle(): int
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->components->error('Blocked in production.');
            return self::FAILURE;
        }

        if ($this->option('cleanup-manifest')) {
            return $this->cleanupFromManifest((string) $this->option('cleanup-manifest'));
        }

        $target = $this->targetFingerprint();
        $applicants = $this->applicantUsers();
        $tables = array_values(array_filter(self::OPERATIONAL_TABLES, fn (string $table): bool => Schema::hasTable($table)));
        $counts = array_map(fn (string $table): array => [$table, DB::table($table)->count()], $tables);

        $this->line('Database fingerprint: '.$target);
        $this->table(['Operational table', 'Rows'], $counts);
        $this->line('Applicant accounts to remove: '.$applicants->count());
        $this->line('Staff accounts, unit configuration and VA pool are preserved.');

        if (! Schema::hasTable('virtual_accounts')) {
            $this->components->error('Missing VA table. Refusing partial reset.');
            return self::FAILURE;
        }

        $unsafeVa = DB::table('virtual_accounts')
            ->whereNotNull('registration_id')
            ->orWhereIn('status', ['assigned', 'paid'])
            ->count();

        if ($unsafeVa) {
            $this->components->error("{$unsafeVa} assigned/paid VA entries require manual reconciliation before reset.");
            return self::FAILURE;
        }

        if (! $this->option('execute')) {
            $this->components->warn('PREVIEW ONLY. To execute, set SPMB_RESET_ALLOWED_TARGET to the fingerprint, verify backups, then supply --execute --target and --backup-confirmed.');
            return self::SUCCESS;
        }

        $configured = (string) config('spmb.reset.allowed_target', '');

        if ($configured === '' || ! hash_equals($target, $configured)
            || ! is_string($this->option('target'))
            || ! hash_equals($target, (string) $this->option('target'))) {
            $this->components->error('Database target not independently allowlisted. No changes made.');
            return self::FAILURE;
        }

        if (! $this->option('backup-confirmed') || ! $this->input->isInteractive()) {
            $this->components->error('A tested database/file backup and an interactive operator are mandatory.');
            return self::FAILURE;
        }

        if (! $this->confirm('Have the database and private-file backups been restored and verified?', false)
            || $this->ask('Type RESET-OPERATIONAL to continue') !== 'RESET-OPERATIONAL') {
            return self::FAILURE;
        }

        $paths = $this->filePaths($tables);
        $snapshot = $this->protectedSnapshot();
        $staff = $this->staffSnapshot();
        $manifestPath = 'reset-manifests/'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.json';
        $manifest = [
            'database_fingerprint' => $target,
            'created_at' => now()->toIso8601String(),
            'file_paths' => $paths,
            'status' => 'prepared',
        ];

        if (! Storage::disk('local')->put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR))) {
            $this->components->error('Unable to persist private-file recovery manifest. No changes made.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($tables, $applicants, $snapshot, $staff): void {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }

                $ids = $applicants->pluck('id')->all();

                if ($ids !== []) {
                    if (Schema::hasTable('notifications')) {
                        DB::table('notifications')->where('notifiable_type', User::class)
                            ->whereIn('notifiable_id', $ids)->delete();
                    }
                    if (Schema::hasTable('push_subscriptions')) {
                        DB::table('push_subscriptions')->where('subscribable_type', User::class)
                            ->whereIn('subscribable_id', $ids)->delete();
                    }
                    foreach (['model_has_roles', 'model_has_permissions'] as $pivot) {
                        if (Schema::hasTable($pivot)) {
                            DB::table($pivot)->where('model_type', User::class)->whereIn('model_id', $ids)->delete();
                        }
                    }
                    if (Schema::hasTable('password_reset_tokens')) {
                        DB::table('password_reset_tokens')->whereIn('email', $applicants->pluck('email')->all())->delete();
                    }
                    if (Schema::hasTable('sessions')) {
                        DB::table('sessions')->whereIn('user_id', $ids)->delete();
                    }

                    // User-owned training/certification activity cascades through
                    // existing foreign keys; master curricula and exams remain.
                    DB::table('users')->whereIn('id', $ids)->delete();
                }

                if ($snapshot !== $this->protectedSnapshot() || $staff !== $this->staffSnapshot()) {
                    throw new \RuntimeException('Preserved configuration or staff changed; database transaction rolled back.');
                }
            }, 3);
        } catch (Throwable $exception) {
            $manifest['status'] = 'database_failed';
            Storage::disk('local')->put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
            $this->components->error('Reset rolled back: '.$exception->getMessage());
            return self::FAILURE;
        }

        $manifest['status'] = 'database_committed';
        Storage::disk('local')->put($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));

        return $this->cleanupFromManifest($manifestPath);
    }

    private function targetFingerprint(): string
    {
        $connection = DB::connection();

        return hash('sha256', implode('|', [
            $connection->getDriverName(),
            (string) $connection->getConfig('host'),
            (string) $connection->getConfig('port'),
            (string) $connection->getDatabaseName(),
        ]));
    }

    private function applicantUsers(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where(function ($query): void {
                $query->whereIn('role', ['pendaftar', 'user'])
                    ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'pendaftar'));
            })
            ->whereNotIn('role', ['super_admin', 'admin_unit', 'tu'])
            ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', ['super_admin', 'admin_unit', 'tu']))
            ->get(['id', 'email']);
    }

    private function protectedSnapshot(): array
    {
        $result = [];

        foreach (self::PROTECTED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $hash = hash_init('sha256');

            $query = DB::table($table);
            if ($columns !== []) {
                $query->orderBy($columns[0]);
            }

            foreach ($query->cursor() as $row) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
            }

            $result[$table] = hash_final($hash);
        }

        return $result;
    }

    private function staffSnapshot(): string
    {
        $staffIds = User::query()
            ->whereIn('role', ['super_admin', 'admin_unit', 'tu'])
            ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['super_admin', 'admin_unit', 'tu']))
            ->pluck('id')->all();

        return hash('sha256', json_encode(
            DB::table('users')->whereIn('id', $staffIds)->orderBy('id')->get()->all(),
            JSON_THROW_ON_ERROR,
        ));
    }

    private function filePaths(array $tables): array
    {
        $paths = [];

        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            $pathFields = array_values(array_filter($columns, fn (string $column): bool => str_ends_with($column, '_path')));
            if ($table === 'registrations' && in_array('custom_answers', $columns, true)) {
                $pathFields[] = 'custom_answers';
            }

            if ($pathFields === []) {
                continue;
            }

            foreach (DB::table($table)->select($pathFields)->cursor() as $row) {
                foreach ($pathFields as $field) {
                    $value = $row->{$field};

                    if ($field === 'custom_answers') {
                        $decoded = json_decode((string) $value, true);
                        if (is_array($decoded)) {
                            $this->findPaths($decoded, $paths);
                        }
                    } elseif (is_string($value)) {
                        $this->findPaths($value, $paths);
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }

    private function findPaths(mixed $value, array &$paths): void
    {
        if (is_array($value)) {
            foreach ($value as $child) {
                $this->findPaths($child, $paths);
            }
            return;
        }

        if (! is_string($value)) {
            return;
        }

        foreach (self::FILE_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)
                && ! str_contains($value, '..')
                && ! str_contains($value, '\\')
                && ! preg_match('/[\x00-\x1f]/', $value)) {
                $paths[] = $value;
                return;
            }
        }
    }

    private function cleanupFromManifest(string $path): int
    {
        if (! preg_match('~^reset-manifests/[a-zA-Z0-9-]+\.json$~', $path)) {
            $this->components->error('Invalid manifest path.');
            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            $this->components->error('Reset manifest not found.');
            return self::FAILURE;
        }

        $manifest = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! in_array($manifest['status'] ?? null, ['database_committed', 'files_incomplete', 'complete'], true)
            || ($manifest['database_fingerprint'] ?? '') !== $this->targetFingerprint()) {
            $this->components->error('Manifest is not committed or belongs to a different database.');
            return self::FAILURE;
        }

        $remaining = [];
        $storage = app(ApplicantFileStorage::class);
        foreach ($manifest['file_paths'] ?? [] as $file) {
            $safePaths = [];
            $this->findPaths($file, $safePaths);
            if ($safePaths === []) {
                continue;
            }

            try {
                $storage->delete($file);
            } catch (Throwable) {
                $remaining[] = $file;
            }
        }

        $manifest['file_paths'] = $remaining;
        $manifest['status'] = $remaining === [] ? 'complete' : 'files_incomplete';
        $disk->put($path, json_encode($manifest, JSON_THROW_ON_ERROR));

        if ($remaining !== []) {
            $this->components->error(count($remaining).' private files remain; use --cleanup-manifest='.$path);
            return self::FAILURE;
        }

        $this->components->info('Operational data reset completed; preserved configurations and staff were checked transactionally.');
        $this->line('Recovery manifest: '.$path);
        return self::SUCCESS;
    }
}
