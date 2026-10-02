<?php

namespace App\Console\Commands;

use App\Models\UserCertification;
use App\Services\SpmbNotificationService;
use App\Services\TrainingGovernanceService;
use Illuminate\Console\Command;

class RemindCertificationExpiry extends Command
{
    protected $signature = 'spmb:remind-certification-expiry';
    protected $description = 'Kirim reminder sertifikasi staff yang mendekati kedaluwarsa.';

    public function handle(): int
    {
        $thresholds = app(TrainingGovernanceService::class)->expiryReminderDays();

        if ($thresholds === []) {
            return self::SUCCESS;
        }

        $max = max($thresholds);
        $sent = 0;

        UserCertification::query()
            ->with('user')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->startOfDay(), now()->addDays($max)->endOfDay()])
            ->chunkById(100, function ($certificates) use ($thresholds, &$sent): void {
                foreach ($certificates as $certificate) {
                    if (! $certificate->user?->is_active) {
                        continue;
                    }

                    $remaining = now()->startOfDay()->diffInDays(
                        $certificate->expires_at->copy()->startOfDay(),
                        false,
                    );

                    if ($remaining < 0) {
                        continue;
                    }

                    $already = collect($certificate->expiry_reminders_sent ?? [])
                        ->mapWithKeys(fn ($value, $key): array => [(string) $key => $value])
                        ->all();

                    $threshold = collect($thresholds)
                        ->sort()
                        ->first(fn (int $day): bool => $remaining <= $day);

                    if ($threshold === null
                        || array_key_exists((string) $threshold, $already)) {
                        continue;
                    }

                    app(SpmbNotificationService::class)
                        ->certificationExpiring($certificate, $remaining);

                    $already[(string) $threshold] = now()->toIso8601String();
                    $certificate->update(['expiry_reminders_sent' => $already]);
                    $sent++;
                }
            });

        $this->info("Reminder sertifikasi terkirim: {$sent}");

        return self::SUCCESS;
    }
}
