<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetOperationalData extends Command
{
    protected $signature = 'spmb:reset-operational {--execute : Execute after reviewing the preview}';

    protected $description = 'Clear SPMB applicant activity while preserving all configuration (non-production only)';

    /**
     * Explicit allowlist: never add configuration, academic-year, unit, VA-pool,
     * user, permission, audit, or infrastructure tables here.
     */
    private const TABLES = [
        'continuation_registration_links',
        'registration_consents',
        'registration_achievements',
        'registration_academic_scores',
        're_registration_items',
        'admission_offers',
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

    public function handle(): int
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->components->error('Blocked: APP_ENV=production.');
            return self::FAILURE;
        }

        $tables = array_values(array_filter(self::TABLES, fn (string $table): bool => Schema::hasTable($table)));
        $this->table(['Operational table', 'Rows'], array_map(
            fn (string $table): array => [$table, DB::table($table)->count()],
            $tables
        ));

        // Bank-issued VA identifiers are retained, and assigned/paid VA cannot
        // be unlinked without a separate bank reconciliation process.
        $unsafeVa = DB::table('virtual_accounts')
            ->where(function ($query): void {
                $query->whereNotNull('registration_id')
                    ->orWhereIn('status', ['assigned', 'paid']);
            })->count();
        if ($unsafeVa > 0) {
            $this->components->error("{$unsafeVa} assigned/paid VAs require bank reconciliation. Reset refused.");
            return self::FAILURE;
        }

        if (! $this->option('execute')) {
            $this->components->warn('Preview only. All unit and registration configuration, staff, and VA pool remain intact.');
            $this->components->warn('Private uploaded files and other operational tables are not covered by this initial command.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Have you backed up the database and applicant files and verified the target database?', false)
            || $this->ask('Type RESET-OPERATIONAL to confirm') !== 'RESET-OPERATIONAL') {
            return self::FAILURE;
        }

        // Never disable foreign keys. Any unforeseen dependency must fail and
        // roll back rather than corrupt retained configuration.
        DB::transaction(function () use ($tables): void {
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }
        });

        $this->components->info('Allowlisted operational rows cleared. Configuration and staff accounts retained.');
        $this->components->warn('Uploaded files and any remaining operational tables require a separate verified cleanup.');
        return self::SUCCESS;
    }
}
