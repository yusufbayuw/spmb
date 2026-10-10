<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
