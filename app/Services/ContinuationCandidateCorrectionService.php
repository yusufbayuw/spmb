<?php

namespace App\Services;

use App\Models\ContinuationCandidate;
use App\Models\RegistrationOpening;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContinuationCandidateCorrectionService
{
    private function year(string $value): string
    {
        return preg_replace('/\s+/', '', str_replace(['–', '—', '-'], '/', trim($value))) ?? trim($value);
    }

    public function correct(ContinuationCandidate $candidate, array $changes): void
    {
        DB::transaction(function () use ($candidate, $changes): void {
            $record = ContinuationCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            $year = $this->year((string) ($changes['academic_year'] ?? $record->academic_year));
            $nik = preg_replace('/\D+/', '', (string) ($changes['nik'] ?? $record->nik));
            $birth = $changes['birth_date'] ?? $record->birth_date;
            $birth = $birth instanceof \DateTimeInterface ? $birth->format('Y-m-d') : (string) $birth;
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $birth);

            if (! preg_match('/^(\d{4})\/(\d{4})$/', $year, $parts)
                || (int) $parts[2] !== (int) $parts[1] + 1
                || ! RegistrationOpening::query()->where('unit_id', $record->unit_id)->where('academic_year', $year)->exists()) {
                throw ValidationException::withMessages(['academic_year' => 'Pilih tahun ajaran valid sesuai pembukaan unit.']);
            }

            if (strlen($nik) !== 16 || ! $date || $date->format('Y-m-d') !== $birth) {
                throw ValidationException::withMessages(['nik' => 'NIK dan tanggal lahir tidak valid.']);
            }

            $key = hash('sha256', 'match|'.$record->unit_id.'|'.$year.'|'.$nik.'|'.$birth);
            $collision = ContinuationCandidate::query()
                ->where('unit_id', $record->unit_id)->where('nik', $nik)
                ->whereDate('birth_date', $birth)->where('id', '!=', $record->id)
                ->get()->contains(fn (ContinuationCandidate $row): bool => $this->year($row->academic_year) === $year);

            if ($collision || ContinuationCandidate::query()->where('source_key', $key)->where('id', '!=', $record->id)->exists()) {
                throw ValidationException::withMessages(['academic_year' => 'Data Terusan duplikat pada unit dan tahun tujuan.']);
            }

            $oldYear = $record->academic_year;
            $record->update([...$changes, 'nik' => $nik, 'birth_date' => $birth, 'academic_year' => $year, 'source_key' => $key]);
            app(AuditTrail::class)->record('continuation_candidate.corrected', $record,
                ['academic_year' => $oldYear], ['academic_year' => $year]);
        });
        app(ContinuationCandidateMatcher::class)->reconcileExistingRegistrations((int) $candidate->unit_id);
    }

    public function moveAcademicYear(int $unitId, string $source, string $target): int
    {
        $source = $this->year($source);
        $target = $this->year($target);
        if ($source === $target || ! RegistrationOpening::query()->where('unit_id', $unitId)->where('academic_year', $target)->exists()) {
            throw ValidationException::withMessages(['target_year' => 'Pilih tahun ajaran tujuan yang berbeda dan tersedia.']);
        }

        $count = DB::transaction(function () use ($unitId, $source, $target): int {
            $all = ContinuationCandidate::query()->where('unit_id', $unitId)->lockForUpdate()->get();
            $moving = $all->filter(fn (ContinuationCandidate $c): bool => $this->year($c->academic_year) === $source);
            if ($moving->isEmpty()) {
                throw ValidationException::withMessages(['source_year' => 'Tidak ditemukan data terusan untuk tahun asal.']);
            }

            $movingIds = $moving->pluck('id')->all();
            $existingKeys = $all->whereNotIn('id', $movingIds)->pluck('source_key')->all();
            $existingIdentities = $all->whereNotIn('id', $movingIds)
                ->filter(fn (ContinuationCandidate $c): bool => $this->year($c->academic_year) === $target)
                ->map(fn (ContinuationCandidate $c): string => $c->nik.'|'.$c->birth_date?->format('Y-m-d'))->all();
            $newKeys = [];
            $identities = [];

            foreach ($moving as $record) {
                $nik = (string) $record->nik;
                $birth = $record->birth_date?->format('Y-m-d');
                $identity = $nik.'|'.$birth;
                $key = hash('sha256', 'match|'.$unitId.'|'.$target.'|'.$nik.'|'.$birth);
                if (strlen($nik) !== 16 || ! $birth || in_array($identity, $existingIdentities, true)
                    || in_array($identity, $identities, true) || in_array($key, $existingKeys, true)) {
                    throw ValidationException::withMessages(['target_year' => 'Pemindahan dibatalkan: identitas duplikat atau tidak valid.']);
                }
                $identities[] = $identity;
                $newKeys[$record->id] = $key;
            }

            foreach ($moving as $record) {
                $record->update(['academic_year' => $target, 'source_key' => $newKeys[$record->id]]);
            }
            app(AuditTrail::class)->record('continuation_candidate.academic_year_bulk_corrected',
                metadata: ['from' => $source, 'to' => $target, 'count' => $moving->count()], unitId: $unitId);
            return $moving->count();
        });

        app(ContinuationCandidateMatcher::class)->reconcileExistingRegistrations($unitId);
        return $count;
    }
}
