<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditTrail;
use Illuminate\Console\Command;

class ReviewHistoricalAudit extends Command
{
    protected $signature = 'spmb:audit:review';
    protected $description = 'Count historical audit records that need privacy review';

    public function handle(AuditTrail $audit): int
    {
        $total = 0;
        $changed = 0;
        AuditLog::query()->chunkById(200, function ($logs) use ($audit, &$total, &$changed): void {
            foreach ($logs as $log) {
                $total++;
                foreach (['old_values', 'new_values', 'metadata'] as $column) {
                    $value = is_array($log->{$column}) ? $log->{$column} : [];
                    if ($audit->redactPayload($value) !== $value) {
                        $changed++;
                        break;
                    }
                }
            }
        });
        $this->info("Audit records scanned: {$total}; requiring review: {$changed}");
        return self::SUCCESS;
    }
}
