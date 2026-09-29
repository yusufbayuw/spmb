<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Database\Eloquent\Builder;
use Database\Seeders\Support\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;

class PaymentReceiptSeeder extends Seeder
{
    use GuardsDemoEnvironment;

    public function run(): void
    {
        if ($this->shouldSkipDemoData()) {
            return;
        }

        Payment::query()
            ->where('status', 'verified')
            ->whereHas('registration.unit', fn (Builder $unitQuery): Builder => $unitQuery->forOperationalMode())
            ->each(fn (Payment $payment) => app(ReceiptService::class)->issue($payment, false));
    }
}
