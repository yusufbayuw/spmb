<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

class PaymentReceiptSeeder extends Seeder
{
    public function run(): void
    {
        Payment::query()
            ->where('status', 'verified')
            ->whereHas('registration.unit', fn (Builder $unitQuery): Builder => $unitQuery->forOperationalMode())
            ->each(fn (Payment $payment) => app(ReceiptService::class)->issue($payment, false));
    }
}
