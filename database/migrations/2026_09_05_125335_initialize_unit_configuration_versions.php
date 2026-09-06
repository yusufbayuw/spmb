<?php

use App\Models\Payment;
use App\Models\Unit;
use App\Services\ReceiptService;
use App\Services\UnitConfigurationService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Unit::query()->each(fn (Unit $unit) => app(UnitConfigurationService::class)->initialize($unit));
        Payment::query()->where('status', 'verified')->each(fn (Payment $payment) => app(ReceiptService::class)->issue($payment, false));
    }

    public function down(): void
    {
        // Published configuration history and issued receipts are retained.
    }
};
