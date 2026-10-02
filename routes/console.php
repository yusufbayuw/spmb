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

Schedule::command('spmb:remind-certification-expiry')
    ->dailyAt('08:00')
    ->withoutOverlapping();
