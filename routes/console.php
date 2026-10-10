<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Cache;
use App\Jobs\ProductionReadinessHeartbeat;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Optional low-impact probes; the web GUI is read-only until an admin saves
// an attestation or snapshot. Both queue workers must process this job.
if (config('spmb.readiness.probes_enabled', false)) {
    Schedule::call(function (): void {
        Cache::put('spmb:readiness:scheduler:last', now()->timestamp, now()->addMinutes(15));
        ProductionReadinessHeartbeat::dispatch('mail');
        ProductionReadinessHeartbeat::dispatch('notifications');
    })->everyFiveMinutes()->name('spmb-production-readiness-probes')->withoutOverlapping();
}

Schedule::command('spmb:expire-admission-offers')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Reminders are opt-in to avoid sending unsolicited messages during deployment.
if (config('spmb.mail.automatic_reminders_enabled', false)) {
    Schedule::command('spmb:remind-pending-actions --execute')->dailyAt('09:00')->withoutOverlapping();
}

Schedule::command('spmb:remind-certification-expiry')
    ->dailyAt('08:00')
    ->withoutOverlapping();
