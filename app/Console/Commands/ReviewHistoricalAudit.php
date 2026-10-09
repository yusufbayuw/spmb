<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReviewHistoricalAudit extends Command
{
    protected $signature = 'spmb:audit:review
        {--execute : Redact historical audit JSON payloads}
        {--backup-confirmed : Confirm that an audited backup and recovery plan exist}';

    protected $description = 'Preview personal-data redaction in historical audit records, optionally apply with approval';

    public function handle(AuditTrail $audit): int
    {
        $execute = (bool) $this->option('execute');

        if ($execute && (! $this->option('backup-confirmed')
            || ! $this->input->isInteractive()
            || ! $this->confirm('Confirm verified backup and privacy review approval?', false))) {
            $this->components->error('Backup approval and interactive confirmation are required. No changes made.');
            return self::FAILURE;
        }

        $scanned = 0;
        $affected = 0;

        AuditLog::query()
            ->select(['id', 'old_values', 'new_values', 'metadata'])
            ->chunkById(200, function ($logs) use ($audit, $execute, &$scanned, &$affected): void {
                DB::transaction(function () use ($logs, $audit, $execute, &$scanned, &$affected): void {
                    foreach ($logs as $log) {
                        $scanned++;
                        $changes = [];

                        foreach (['old_values', 'new_values', 'metadata'] as $field) {
                            $original = $log->{$field};
                            if (! is_array($original)) {
                                continue;
                            }

                            $sanitized = $audit->redactPayload($original);

                            if ($sanitized !== $original) {
                                $changes[$field] = json_encode($sanitized, JSON_THROW_ON_ERROR);
                            }
                        }

                        if ($changes !== []) {
                            $affected++;
                            if ($execute) {
                                // Purposefully bypass the immutable AuditLog model
                                // only for approved, non-reversible field redaction.
                                DB::table('audit_logs')->where('id', $log->id)->update($changes);
                            }
                        }
                    }
                });
            });

        $message = $execute ? 'Redacted' : 'Would redact';
        $this->components->info("{$message} {$affected} of {$scanned} historical audit records.");

        if ($execute) {
            $audit->record('privacy.historical_audit_redacted', metadata: [
                'records_examined' => $scanned,
                'records_changed' => $affected,
            ]);
        }

        $this->components->warn('Only audit JSON payloads are included. Free-text descriptions, request URLs, IP addresses, and pre-existing backups require separate review.');
        return self::SUCCESS;
    }
}
