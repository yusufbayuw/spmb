<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationNumberService
{
    public function assign(Registration $registration): string
    {
        if (filled($registration->registration_number)) {
            return (string) $registration->registration_number;
        }

        $registration->loadMissing(['configuration', 'opening', 'unit']);

        $paymentRequired = $registration->configuration?->payment_enabled ?? true;

        if ($paymentRequired && ! $registration->payment_verified_at) {
            throw ValidationException::withMessages([
                'registration_number' => 'Nomor registrasi baru diterbitkan setelah pembayaran VA diverifikasi.',
            ]);
        }

        if (! $paymentRequired && $registration->data_validation_status !== 'valid') {
            throw ValidationException::withMessages([
                'registration_number' => 'Nomor registrasi baru diterbitkan setelah data pendaftaran dinyatakan valid.',
            ]);
        }

        DB::table('registration_number_sequences')->insertOrIgnore([
            'unit_id' => $registration->unit_id,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('registration_number_sequences')
            ->where('unit_id', $registration->unit_id)
            ->lockForUpdate()
            ->first();

        $next = ((int) ($sequence?->last_number ?? 0)) + 1;

        DB::table('registration_number_sequences')
            ->where('unit_id', $registration->unit_id)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        $year = $registration->opening?->academic_year
            ? str_replace(['/', '-'], '', $registration->opening->academic_year)
            : ($registration->created_at?->format('Y') ?? now()->format('Y'));

        $number = 'REG-'.$registration->unit->code.'-'.$year.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        $registration->forceFill([
            'registration_number' => $number,
        ])->saveQuietly();

        return $number;
    }
}
