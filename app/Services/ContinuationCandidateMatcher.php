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

        $academicYear = $this->normalizeAcademicYear($opening->academic_year);

        return ContinuationCandidate::query()
            ->matchable()
            ->where('unit_id', $opening->unit_id)
            ->where('nik', $nik)
            ->whereDate('birth_date', $birthDate)
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->get()
            ->first(fn (ContinuationCandidate $candidate): bool =>
                $this->normalizeAcademicYear($candidate->academic_year) === $academicYear
            );
    }

    /** @return array<string, mixed> */
    public function prefill(ContinuationCandidate $candidate): array
    {
        $raw = is_array($candidate->raw_data) ? $candidate->raw_data : [];

        $fallback = array_filter([
            'full_name' => $candidate->full_name ?: $this->raw($raw, ['nama', 'nama lengkap']),
            'gender' => $this->gender($this->raw($raw, ['jk', 'jenis kelamin'])),
            'religion' => $this->religion($this->raw($raw, ['agama'])),
            'birth_place' => $this->string($this->raw($raw, ['tempat lahir'])),
            'home_address' => $this->string($this->raw($raw, ['alamat', 'alamat rumah'])),
            'rt' => $this->digits($this->raw($raw, ['rt'])),
            'rw' => $this->digits($this->raw($raw, ['rw'])),
            'phone' => $this->string($this->raw($raw, ['hp', 'no hp', 'nomor hp', 'telepon'])),
            'email' => $this->email($this->raw($raw, ['e-mail', 'email'])),
            'previous_school' => $candidate->source_school_name ?: $this->string($this->raw($raw, ['sekolah asal', 'asal sekolah', 'nama sekolah', 'sekolah'])),
            'parentInfo.father_name' => $this->string($this->raw($raw, ['data ayah nama'])),
            'parentInfo.father_nik' => $this->digits($this->raw($raw, ['data ayah nik'])),
            'parentInfo.father_education' => $this->education($this->raw($raw, ['data ayah jenjang pendidikan', 'data ayah pendidikan'])),
            'parentInfo.father_occupation' => $this->string($this->raw($raw, ['data ayah pekerjaan'])),
            'parentInfo.mother_name' => $this->string($this->raw($raw, ['data ibu nama'])),
            'parentInfo.mother_nik' => $this->digits($this->raw($raw, ['data ibu nik'])),
            'parentInfo.mother_education' => $this->education($this->raw($raw, ['data ibu jenjang pendidikan', 'data ibu pendidikan'])),
            'parentInfo.mother_occupation' => $this->string($this->raw($raw, ['data ibu pekerjaan'])),
        ], fn (mixed $value): bool => ! blank($value));

        return collect([
            ...$fallback,
            ...(is_array($candidate->prefill_data) ? $candidate->prefill_data : []),
        ])
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

    /** @param array<string,mixed> $raw @param list<string> $aliases */
    private function raw(array $raw, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $raw) && ! blank($raw[$alias])) {
                return $raw[$alias];
            }
        }

        return null;
    }

    private function string(mixed $value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) ($value ?? ''))) ?? '';

        return $value !== '' ? $value : null;
    }

    private function digits(mixed $value): ?string
    {
        $value = $this->string($value);
        if (! $value) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : null;
    }

    private function gender(mixed $value): ?string
    {
        return match (mb_strtolower($this->string($value) ?? '')) {
            'l', 'laki-laki', 'laki laki', 'male' => 'L',
            'p', 'perempuan', 'female' => 'P',
            default => null,
        };
    }

    private function religion(mixed $value): ?string
    {
        return match (mb_strtolower($this->string($value) ?? '')) {
            'islam' => 'Islam',
            'kristen', 'protestan', 'kristen protestan' => 'Kristen',
            'katolik', 'katholik' => 'Katolik',
            'hindu' => 'Hindu',
            'buddha', 'budha' => 'Buddha',
            'konghucu', 'khonghucu' => 'Konghucu',
            default => null,
        };
    }

    private function education(mixed $value): ?string
    {
        $value = mb_strtoupper($this->string($value) ?? '');

        foreach (['S3', 'S2', 'S1', 'D4', 'D3', 'D2', 'D1', 'SMA', 'SMK', 'SMP', 'SD'] as $level) {
            if (str_contains($value, $level)) {
                return $level === 'SMK' ? 'SMA' : $level;
            }
        }

        return $value !== '' ? 'Lainnya' : null;
    }

    private function email(mixed $value): ?string
    {
        $value = mb_strtolower($this->string($value) ?? '');

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function normalizeAcademicYear(mixed $value): string
    {
        $value = trim((string) $value);
        $value = str_replace(['–', '—', '-'], '/', $value);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        if (preg_match('/^(\d{4})\/(\d{4})$/', $value, $matches)) {
            return $matches[1].'/'.$matches[2];
        }

        return $value;
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
