<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationNumberService
{
    public const MODE_UNIT = 'per_unit';

    public const MODE_WAVE = 'per_wave';

    public const MODE_LABELS = [
        self::MODE_UNIT => 'Per Unit (nomor berlanjut)',
        self::MODE_WAVE => 'Per Gelombang (reset setiap gelombang)',
    ];

    public function modeFor(RegistrationOpening $opening): string
    {
        return $this->modeForGroup((int) $opening->unit_id, (string) $opening->academic_year, (string) $opening->wave);
    }

    public function modeForGroup(int $unitId, string $academicYear, string $wave): string
    {
        return DB::table('registration_number_wave_sequences')
            ->where('unit_id', $unitId)
            ->where('academic_year', $academicYear)
            ->where('wave', $wave)
            ->exists() ? self::MODE_WAVE : self::MODE_UNIT;
    }

    public function groupHasIssuedNumbers(RegistrationOpening $opening): bool
    {
        return Registration::query()
            ->where('unit_id', $opening->unit_id)
            ->whereNotNull('registration_number')
            ->whereHas('opening', fn (Builder $query): Builder => $query
                ->where('unit_id', $opening->unit_id)
                ->where('academic_year', $opening->academic_year)
                ->where('wave', $opening->wave))
            ->exists();
    }

    /**
     * Changing the numbering mode never renumbers issued registrations.
     * A unit row lock serializes this operation against all number issuance in that unit.
     * Openings in different study programs with the same unit, year and wave share one mode.
     */
    public function changeMode(RegistrationOpening $opening, string $mode): void
    {
        if (! array_key_exists($mode, self::MODE_LABELS)) {
            throw ValidationException::withMessages(['mode' => 'Mode penomoran tidak dikenal.']);
        }

        DB::transaction(function () use ($opening, $mode): void {
            DB::table('units')->where('id', $opening->unit_id)->lockForUpdate()->first();

            // Refetch after the lock so an edited opening cannot change a stale group.
            $opening = RegistrationOpening::query()->findOrFail($opening->id);
            if ($this->modeFor($opening) === $mode) {
                return;
            }

            if ($this->groupHasIssuedNumbers($opening)) {
                throw ValidationException::withMessages([
                    'mode' => 'Mode penomoran sudah terkunci karena nomor registrasi pertama telah diterbitkan pada unit, tahun ajaran, dan gelombang ini.',
                ]);
            }

            $group = [
                'unit_id' => $opening->unit_id,
                'academic_year' => $opening->academic_year,
                'wave' => $opening->wave,
            ];

            if ($mode === self::MODE_UNIT) {
                DB::table('registration_number_wave_sequences')->where($group)->delete();

                return;
            }

            $waveCode = $this->waveCode((string) $opening->wave);
            if (DB::table('registration_number_wave_sequences')
                ->where('unit_id', $opening->unit_id)
                ->where('academic_year', $opening->academic_year)
                ->where('wave_code', $waveCode)
                ->where('wave', '!=', $opening->wave)
                ->exists()) {
                throw ValidationException::withMessages([
                    'mode' => 'Kode gelombang bentrok dengan gelombang lain. Gunakan nama gelombang yang berbeda.',
                ]);
            }

            DB::table('registration_number_wave_sequences')->insert([
                ...$group,
                'wave_code' => $waveCode,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

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

        // Enclose the counter lock and registration update in one atomic transaction,
        // even when a caller does not already hold a transaction.
        return DB::transaction(function () use ($registration): string {
            DB::table('units')->where('id', $registration->unit_id)->lockForUpdate()->first();

            $opening = $registration->opening;
            $wave = $opening
                ? DB::table('registration_number_wave_sequences')
                    ->where('unit_id', $registration->unit_id)
                    ->where('academic_year', $opening->academic_year)
                    ->where('wave', $opening->wave)
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($wave) {
                $next = ((int) $wave->last_number) + 1;
                DB::table('registration_number_wave_sequences')
                    ->where('id', $wave->id)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                // The original per-unit counter and its formatting remain unchanged.
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
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            }

            $digits = max(3, min(12, (int) ($registration->configuration?->registration_number_digits ?? 4)));
            $prefix = filled($registration->configuration?->registration_number_prefix)
                ? strtoupper(trim((string) $registration->configuration->registration_number_prefix))
                : null;

            if ($wave) {
                $year = preg_replace('/\D+/', '', (string) $opening->academic_year);
                $number = ($prefix ?: 'REG-'.$registration->unit->code).'-'.$year.'-'.$wave->wave_code.'-'.str_pad((string) $next, $digits, '0', STR_PAD_LEFT);
            } elseif ($prefix) {
                $number = $prefix.'-'.str_pad((string) $next, $digits, '0', STR_PAD_LEFT);
            } else {
                $year = $opening?->academic_year
                    ? str_replace(['/', '-'], '', $opening->academic_year)
                    : ($registration->created_at?->format('Y') ?? now()->format('Y'));

                $number = 'REG-'.$registration->unit->code.'-'.$year.'-'.str_pad((string) $next, $digits, '0', STR_PAD_LEFT);
            }

            if (strlen($number) > 100) {
                throw ValidationException::withMessages(['registration_number' => 'Nomor registrasi melebihi batas 100 karakter. Perpendek prefix penomoran.']);
            }

            $registration->forceFill(['registration_number' => $number])->saveQuietly();

            return $number;
        });
    }

    private function waveCode(string $wave): string
    {
        $label = trim($wave);
        if (preg_match('/^(?:gelombang|wave|g)\s*[-#:]?\s*(\d{1,4})$/iu', $label, $matches)) {
            return 'G'.(int) $matches[1];
        }

        // Names other than "Gelombang N" still receive a stable and short code.
        return 'W'.strtoupper(substr(sha1(mb_strtolower($label)), 0, 8));
    }
}
