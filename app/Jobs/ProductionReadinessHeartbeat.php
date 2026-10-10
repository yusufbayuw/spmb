<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ProductionReadinessHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 15;

    public function __construct(public string $probe)
    {
        $this->onQueue((string) config('spmb.'.$probe.'.queue', $probe));
    }

    public function handle(): void
    {
        if (! in_array($this->probe, ['mail', 'notifications'], true)) {
            return;
        }

        Cache::put('spmb:readiness:worker:'.$this->probe, now()->timestamp, now()->addMinutes(15));
    }
}
