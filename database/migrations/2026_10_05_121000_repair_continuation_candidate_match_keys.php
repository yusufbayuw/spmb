<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('continuation_candidates')) {
            return;
        }

        DB::table('continuation_candidates')
            ->where(function ($query): void {
                $query->whereNull('nik')->orWhereNull('birth_date');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $raw = json_decode((string) ($row->raw_data ?? ''), true);
                    $raw = is_array($raw) ? $raw : [];

                    $nik = filled($row->nik)
                        ? (string) $row->nik
                        : $this->normalizeNik($raw['nik'] ?? null);
                    $birthDate = filled($row->birth_date)
                        ? (string) $row->birth_date
                        : $this->normalizeDate($raw['tanggal lahir'] ?? $raw['tgl lahir'] ?? null);

                    if (! $nik && ! $birthDate) {
                        continue;
                    }

                    $updates = [
                        'nik' => $nik,
                        'birth_date' => $birthDate,
                        'updated_at' => now(),
                    ];

                    if ($nik && $birthDate) {
                        $canonicalKey = hash(
                            'sha256',
                            'match|'.$row->unit_id.'|'.$row->academic_year.'|'.$nik.'|'.$birthDate,
                        );

                        $canonicalId = DB::table('continuation_candidates')
                            ->where('source_key', $canonicalKey)
                            ->where('id', '!=', $row->id)
                            ->value('id');

                        if ($canonicalId) {
                            DB::table('continuation_candidates')
                                ->where('id', $row->id)
                                ->update([
                                    ...$updates,
                                    'is_active' => false,
                                ]);

                            continue;
                        }

                        $updates['source_key'] = $canonicalKey;
                    }

                    DB::table('continuation_candidates')
                        ->where('id', $row->id)
                        ->update($updates);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Data repair is intentionally not reversed.
    }

    private function normalizeNik(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            $safeIntegerLimit = 9007199254740991;

            if (! is_finite($value)
                || floor($value) !== $value
                || abs($value) > $safeIntegerLimit) {
                return null;
            }

            $value = sprintf('%.0f', $value);
        }

        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        return strlen($digits) === 16 ? $digits : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
};
