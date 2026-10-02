<?php

namespace App\Services;

use App\Models\AppSetting;

class TrainingGovernanceService
{
    public const ENFORCEMENT_MODES = [
        'off' => 'Nonaktif',
        'warning' => 'Peringatan Saja',
        'sensitive_actions' => 'Wajib untuk Operasi Sensitif',
        'full_role' => 'Wajib untuk Mutasi Role',
    ];

    public function settings(): ?AppSetting
    {
        return AppSetting::query()->first();
    }

    public function sequenceEnforced(): bool
    {
        return (bool) ($this->settings()?->training_enforce_sequence ?? true);
    }

    public function masteryRequired(): bool
    {
        return (bool) ($this->settings()?->training_require_module_mastery ?? true);
    }

    public function enforcementMode(): string
    {
        $mode = (string) ($this->settings()?->certification_enforcement_mode ?? 'off');

        return array_key_exists($mode, self::ENFORCEMENT_MODES) ? $mode : 'off';
    }

    public function expiryReminderDays(): array
    {
        $days = $this->settings()?->certification_expiry_reminder_days;

        if (! is_array($days) || $days === []) {
            return [30, 14, 7, 1];
        }

        return collect($days)
            ->map(fn (mixed $day): int => max(1, (int) $day))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function certificateArtifactEnabled(): bool
    {
        return (bool) ($this->settings()?->certificate_artifact_enabled ?? true);
    }
}
