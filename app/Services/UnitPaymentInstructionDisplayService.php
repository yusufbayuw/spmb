<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\UnitPaymentInstruction;

class UnitPaymentInstructionDisplayService
{
    /** @return array{payment_page: ?string, next_action: ?string, payment_summary: ?string} */
    public function forRegistration(Registration $registration): array
    {
        $hidden = [
            'payment_page' => null,
            'next_action' => null,
            'payment_summary' => null,
        ];

        if (! $registration->isOperational()
            || ! in_array($registration->current_stage, ['payment', 'payment_verification'], true)
            || $registration->payment_verified_at !== null) {
            return $hidden;
        }

        $payment = $registration->latestPayment;
        // "paid" means proof uploaded and awaiting review; rejected proof may be resent.
        if (! $payment || $payment->status === 'verified') {
            return $hidden;
        }

        $instruction = UnitPaymentInstruction::query()
            ->where('unit_id', $registration->unit_id)
            ->first();

        if (! $instruction || blank($instruction->content)) {
            return $hidden;
        }

        $html = app(RegistrationConsentService::class)->sanitizeHtml((string) $instruction->content);
        if (blank(trim(strip_tags($html)))) {
            return $hidden;
        }

        return [
            'payment_page' => $instruction->show_on_payment_page ? $html : null,
            'next_action' => $instruction->show_on_next_action ? $html : null,
            'payment_summary' => $instruction->show_on_payment_summary ? $html : null,
        ];
    }
}
