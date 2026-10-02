<?php

namespace App\Services;

use App\Models\ContinuationCandidate;
use App\Models\ContinuationRegistrationLink;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

class ContinuationCandidateMatcher
{
    public function fingerprint(mixed $openingUuid, mixed $nik, mixed $birthDate): ?string
    {
        $openingUuid = trim((string) $openingUuid);
        $nik = $this->normalizeNik($nik);
        $birthDate = $this->normalizeDate($birthDate);

        if ($openingUuid === '' || ! $nik || ! $birthDate) {
            return null;
        }

        return hash('sha256', $openingUuid.'|'.$nik.'|'.$birthDate);
    }

    public function match(mixed $openingUuid, mixed $nik, mixed $birthDate): ?ContinuationCandidate
    {
        $openingUuid = trim((string) $openingUuid);
        $nik = $this->normalizeNik($nik);
        $birthDate = $this->normalizeDate($birthDate);

        if ($openingUuid === '' || ! $nik || ! $birthDate) {
            return null;
        }

        $opening = RegistrationOpening::query()
            ->where('uuid', $openingUuid)
            ->first(['id', 'unit_id', 'academic_year']);

        if (! $opening) {
            return null;
        }

        return $this->matchForOpening($opening, $nik, $birthDate);
    }

    public function matchForOpening(
        RegistrationOpening $opening,
        mixed $nik,
        mixed $birthDate,
    ): ?ContinuationCandidate {
        $nik = $this->normalizeNik($nik);
        $birthDate = $this->normalizeDate($birthDate);

        if (! $nik || ! $birthDate) {
            return null;
        }

        $matches = ContinuationCandidate::query()
            ->matchable()
            ->where('unit_id', $opening->unit_id)
            ->where('academic_year', $opening->academic_year)
            ->where('nik', $nik)
            ->where('birth_date', $birthDate)
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @return array<string, mixed> */
    public function prefill(ContinuationCandidate $candidate): array
    {
        return collect($candidate->prefill_data ?? [])
            ->filter(fn (mixed $value): bool => ! blank($value))
            ->all();
    }

    public function linkRegistration(Registration $registration): void
    {
        $registration->loadMissing('opening');

        if (! $registration->opening) {
            return;
        }

        $candidate = $this->matchForOpening(
            $registration->opening,
            $registration->nik,
            $registration->birth_date,
        );

        if (! $candidate) {
            return;
        }

        ContinuationRegistrationLink::query()->updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'continuation_candidate_id' => $candidate->id,
                'matched_by' => 'nik_birth_date',
                'source_snapshot' => [
                    'candidate_uuid' => $candidate->uuid,
                    'unit_id' => $candidate->unit_id,
                    'academic_year' => $candidate->academic_year,
                    'source_school_name' => $candidate->source_school_name,
                    'nik' => $candidate->nik,
                    'birth_date' => $candidate->birth_date?->format('Y-m-d'),
                    'full_name' => $candidate->full_name,
                    'nisn' => $candidate->nisn,
                    'nipd' => $candidate->nipd,
                    'raw_data' => $candidate->raw_data,
                ],
                'prefilled_fields' => array_keys($this->prefill($candidate)),
                'matched_at' => now(),
            ],
        );
    }

    private function normalizeNik(mixed $value): ?string
    {
        if (is_float($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim((string) $value)) ?? '';

        return strlen($digits) === 16 ? $digits : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);

                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
            }
        }

        return null;
    }
}
