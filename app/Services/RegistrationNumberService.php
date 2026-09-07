<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Support\Facades\DB;

class RegistrationNumberService
{
    public function assign(Registration $registration): string
    {
        if (filled($registration->registration_number)) {
            return (string) $registration->registration_number;
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
