<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneHistoricalAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_does_not_change_historical_records(): void
    {
        $log = $this->legacyRecord();

        $this->artisan('spmb:audit:review')->assertExitCode(0);

        $this->assertSame('Old Example', $log->fresh()->old_values['full_name']);
        $this->assertSame('1234567890123456', $log->fresh()->metadata['nested']['nik']);
    }

    public function test_apply_requires_tested_backup_confirmation(): void
    {
        $log = $this->legacyRecord();

        $this->artisan('spmb:audit:review', ['--execute' => true])
            ->assertExitCode(1);

        $this->assertSame('Old Example', $log->fresh()->old_values['full_name']);
    }

    public function test_apply_redacts_nested_personal_fields_without_losing_audit_identity(): void
    {
        $log = $this->legacyRecord();

        $this->artisan('spmb:audit:review', [
            '--execute' => true,
            '--backup-confirmed' => true,
        ])
            ->expectsConfirmation('Confirm verified backup and privacy review approval?', 'yes')
            ->assertExitCode(0);

        $record = $log->fresh();
        $this->assertSame($log->id, $record->id);
        $this->assertSame('registration.updated', $record->event);
        $this->assertSame('[REDACTED]', $record->old_values['full_name']);
        $this->assertSame('[REDACTED]', $record->metadata['nested']['nik']);
        $this->assertSame('active', $record->new_values['lifecycle_status']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'privacy.historical_audit_redacted']);
    }

    private function legacyRecord(): AuditLog
    {
        $unit = Unit::create(['name' => 'Security Unit', 'code' => 'SEC-AUDIT', 'is_active' => true]);

        return AuditLog::query()->create([
            'unit_id' => $unit->id,
            'event' => 'registration.updated',
            'description' => 'Pendaftaran diubah',
            'old_values' => ['full_name' => 'Old Example'],
            'new_values' => ['lifecycle_status' => 'active'],
            'metadata' => ['nested' => ['nik' => '1234567890123456']],
            'created_at' => now(),
        ]);
    }
}
