<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReviewHistoricalAudit extends Command
{
    protected $signature = 'spmb:audit:review {--execute} {--backup-confirmed}';
    protected $description = 'Count historical audit records that need privacy review';

    public function handle(AuditTrail $audit): int
    {
        if ($this->option('execute') && (! $this->option('backup-confirmed') || ! $this->confirm('Confirm verified backup and privacy review approval?', false))) {
            $this->error('Remediation requires explicit confirmation and backup.');
            return self::FAILURE;
        }

        $total = 0;
        $changed = 0;
        AuditLog::query()->chunkById(200, function ($logs) use ($audit, &$total, &$changed): void {
            foreach ($logs as $log) {
                $total++;
                foreach (['old_values', 'new_values', 'metadata'] as $column) {
                    $value = is_array($log->{$column}) ? $log->{$column} : [];
                    if ($audit->redactPayload($value) !== $value) {
                        $changed++;
                        if ($this->option('execute')) {
                            DB::table('audit_logs')->where('id', $log->id)->update([$column => json_encode($audit->redactPayload($value), JSON_THROW_ON_ERROR)]);
                        }
                    }
                }
            }
        });
        $this->info("Audit records scanned: {$total}; requiring review: {$changed}");
        return self::SUCCESS;
    }
}
