<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiptService
{
    public function issue(Payment $payment, bool $notify = true): PaymentReceipt
    {
        return DB::transaction(function () use ($payment, $notify): PaymentReceipt {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'verified') {
                throw ValidationException::withMessages(['payment' => 'Kuitansi hanya tersedia untuk pembayaran terverifikasi.']);
            }
            $existing = PaymentReceipt::where('payment_id', $payment->id)->first();
            if ($existing) {
                return $existing;
            }
            $registration = $payment->registration;
            $receipt = PaymentReceipt::create([
                'payment_id' => $payment->id,
                'number' => 'KW-'.$registration->unit->code.'-'.now()->format('Y').'-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                'issued_at' => now(),
                'details' => ['participant' => $registration->full_name, 'registration_number' => $registration->registration_number, 'unit' => $registration->unit->name, 'period' => $registration->opening?->academic_year, 'wave' => $registration->opening?->wave, 'amount' => $payment->amount, 'va_number' => $payment->va_number, 'bank' => $payment->virtualAccount?->bank, 'payment_date' => $payment->payment_date?->toDateTimeString(), 'verified_at' => $payment->verified_at?->toDateTimeString()],
            ]);
            if ($notify) {
                app(SpmbNotificationService::class)->workflowEvent($registration, 'receipt.issued.'.$payment->id, 'Kuitansi pembayaran tersedia', 'Pembayaran telah diverifikasi. Kuitansi '.$receipt->number.' dapat dicetak.', true, false);
            }

            return $receipt;
        });
    }
}
