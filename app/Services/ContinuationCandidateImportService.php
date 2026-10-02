<?php

namespace App\Services;

use App\Models\ContinuationCandidate;
use DateTimeInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

class ContinuationCandidateImportService
{
    private const PARENT_GROUPS = ['data ayah', 'data ibu', 'data wali'];

    /**
     * @return array{processed:int,created:int,updated:int,skipped:int,errors:list<string>}
     */
    public function import(string $absolutePath, int $unitId, string $academicYear, int $actorId, string $sourceFile): array
    {
        $reader = $this->readerFor($absolutePath);
        $reader->open($absolutePath);

        $result = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $buffer = [];
        $batchUuid = (string) Str::uuid();

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $firstHeader = null;
                $headers = null;
                $rowNumber = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $values = $row->toArray();

                    if ($rowNumber === 1) {
                        $firstHeader = $values;
                        continue;
                    }

                    if ($rowNumber === 2) {
                        $secondary = $this->usesSecondaryHeader($firstHeader ?? [], $values);
                        $headers = $this->buildHeaders($firstHeader ?? [], $secondary ? $values : []);

                        if ($secondary) {
                            continue;
                        }
                    }

                    if (! $headers) {
                        continue;
                    }

                    try {
                        $candidate = $this->candidateRow($headers, $values, $unitId, $academicYear, $actorId, $sourceFile, $batchUuid, $rowNumber);
                    } catch (Throwable $exception) {
                        $result['skipped']++;

                        if (count($result['errors']) < 20) {
                            $result['errors'][] = 'Baris '.$rowNumber.': '.$exception->getMessage();
                        }

                        continue;
                    }

                    if ($candidate === null) {
                        continue;
                    }

                    $buffer[] = $candidate;
                    $result['processed']++;

                    if (count($buffer) >= 500) {
                        $this->flush($buffer, $result);
                        $buffer = [];
                    }
                }

                break;
            }

            if ($buffer !== []) {
                $this->flush($buffer, $result);
            }
        } finally {
            $reader->close();
        }

        return $result;
    }

    private function readerFor(string $path): CsvReader|XlsxReader
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'csv' => new CsvReader(),
            'xlsx' => new XlsxReader(),
            default => throw ValidationException::withMessages(['file' => 'Format file Terusan harus .xlsx atau .csv.']),
        };
    }

    /** @param list<mixed> $first @param list<mixed> $second */
    private function usesSecondaryHeader(array $first, array $second): bool
    {
        $top = collect($first)->map(fn (mixed $value): string => $this->header($value));
        if ($top->contains(fn (string $value): bool => in_array($value, self::PARENT_GROUPS, true))) {
            return true;
        }

        $known = ['nama', 'tahun lahir', 'jenjang pendidikan', 'pekerjaan', 'penghasilan', 'nik'];
        $count = collect($second)
            ->map(fn (mixed $value): string => $this->header($value))
            ->filter(fn (string $value): bool => in_array($value, $known, true))
            ->count();

        return $count >= 3;
    }

    /** @param list<mixed> $first @param list<mixed> $second @return list<string> */
    private function buildHeaders(array $first, array $second): array
    {
        $count = max(count($first), count($second));
        $headers = [];
        $group = null;

        for ($index = 0; $index < $count; $index++) {
            $top = $this->header($first[$index] ?? null);
            $sub = $this->header($second[$index] ?? null);

            if (in_array($top, self::PARENT_GROUPS, true)) {
                $group = $top;
            } elseif ($top !== '') {
                $group = null;
            }

            if ($group && $sub !== '') {
                $headers[] = $group.' '.$sub;
            } elseif ($top !== '' && ! in_array($top, self::PARENT_GROUPS, true)) {
                $headers[] = $top;
            } else {
                $headers[] = $sub !== '' ? $sub : 'kolom '.($index + 1);
            }
        }

        return $headers;
    }

    /**
     * @param list<string> $headers
     * @param list<mixed> $values
     * @return array<string, mixed>|null
     */
    private function candidateRow(array $headers, array $values, int $unitId, string $academicYear, int $actorId, string $sourceFile, string $batchUuid, int $rowNumber): ?array
    {
        $raw = [];
        foreach ($headers as $index => $header) {
            $raw[$header] = $this->rawValue($values[$index] ?? null);
        }

        if (collect($raw)->every(fn (mixed $value): bool => blank($value))) {
            return null;
        }

        $sourceSchool = $this->string($this->value($raw, ['sekolah asal', 'asal sekolah', 'sekolah']));
        if (! $sourceSchool) {
            throw ValidationException::withMessages(['source_school_name' => 'Sekolah Asal wajib tersedia.']);
        }

        $nik = $this->digits($this->value($raw, ['nik']), 16, true);
        $birthDate = $this->date($this->value($raw, ['tanggal lahir', 'tgl lahir']));
        $fullName = $this->string($this->value($raw, ['nama', 'nama lengkap']));
        $nisn = $this->digits($this->value($raw, ['nisn']));
        $nipd = $this->string($this->value($raw, ['nipd']));

        $prefill = array_filter([
            'full_name' => $fullName,
            'gender' => $this->gender($this->value($raw, ['jk', 'jenis kelamin'])),
            'religion' => $this->religion($this->value($raw, ['agama'])),
            'birth_place' => $this->string($this->value($raw, ['tempat lahir'])),
            'home_address' => $this->string($this->value($raw, ['alamat', 'alamat rumah'])),
            'rt' => $this->digits($this->value($raw, ['rt'])),
            'rw' => $this->digits($this->value($raw, ['rw'])),
            'phone' => $this->string($this->value($raw, ['hp', 'no hp', 'nomor hp', 'telepon'])),
            'email' => $this->email($this->value($raw, ['e-mail', 'email'])),
            'previous_school' => $sourceSchool,
            'parentInfo.father_name' => $this->string($this->value($raw, ['data ayah nama'])),
            'parentInfo.father_nik' => $this->digits($this->value($raw, ['data ayah nik']), 16, true),
            'parentInfo.father_education' => $this->education($this->value($raw, ['data ayah jenjang pendidikan', 'data ayah pendidikan'])),
            'parentInfo.father_occupation' => $this->string($this->value($raw, ['data ayah pekerjaan'])),
            'parentInfo.mother_name' => $this->string($this->value($raw, ['data ibu nama'])),
            'parentInfo.mother_nik' => $this->digits($this->value($raw, ['data ibu nik']), 16, true),
            'parentInfo.mother_education' => $this->education($this->value($raw, ['data ibu jenjang pendidikan', 'data ibu pendidikan'])),
            'parentInfo.mother_occupation' => $this->string($this->value($raw, ['data ibu pekerjaan'])),
        ], fn (mixed $value): bool => ! blank($value));

        $sourceKey = $this->sourceKey($unitId, $academicYear, $nik, $birthDate, $nisn, $nipd, $fullName, $sourceSchool, $raw);

        return [
            'uuid' => (string) Str::uuid(),
            'unit_id' => $unitId,
            'academic_year' => $academicYear,
            'source_school_name' => $sourceSchool,
            'source_file' => $sourceFile,
            'import_batch_uuid' => $batchUuid,
            'source_row' => $rowNumber,
            'source_key' => $sourceKey,
            'nik' => $nik,
            'birth_date' => $birthDate,
            'full_name' => $fullName,
            'nisn' => $nisn,
            'nipd' => $nipd,
            'prefill_data' => json_encode($prefill, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'raw_data' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_active' => true,
            'imported_by' => $actorId,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** @param list<array<string,mixed>> $buffer @param array{processed:int,created:int,updated:int,skipped:int,errors:list<string>} $result */
    private function flush(array $buffer, array &$result): void
    {
        $keys = array_column($buffer, 'source_key');
        $existing = array_fill_keys(ContinuationCandidate::query()->whereIn('source_key', $keys)->pluck('source_key')->all(), true);

        foreach ($buffer as $row) {
            isset($existing[$row['source_key']]) ? $result['updated']++ : $result['created']++;
        }

        ContinuationCandidate::query()->upsert($buffer, ['source_key'], [
            'unit_id', 'academic_year', 'source_school_name', 'source_file', 'import_batch_uuid', 'source_row',
            'nik', 'birth_date', 'full_name', 'nisn', 'nipd', 'prefill_data', 'raw_data', 'is_active',
            'imported_by', 'imported_at', 'updated_at',
        ]);
    }

    /** @param array<string,mixed> $raw @param list<string> $aliases */
    private function value(array $raw, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            $alias = $this->header($alias);
            if (array_key_exists($alias, $raw) && ! blank($raw[$alias])) {
                return $raw[$alias];
            }
        }

        return null;
    }

    private function header(mixed $value): string
    {
        $value = str_ireplace(['<br>', '<br/>', '<br />'], ' ', (string) $value);
        $value = preg_replace('/\s+/u', ' ', trim(strip_tags($value))) ?? '';

        return mb_strtolower($value);
    }

    private function rawValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value) && floor($value) === $value) {
            return sprintf('%.0f', $value);
        }

        return $value;
    }

    private function string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = sprintf('%.0f', $value);
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';

        return $value !== '' ? $value : null;
    }

    private function digits(mixed $value, ?int $exactLength = null, bool $rejectFloat = false): ?string
    {
        if ($value === null || ($rejectFloat && is_float($value))) {
            return null;
        }

        $value = $this->string($value);
        if (! $value) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '' || ($exactLength !== null && strlen($digits) !== $exactLength)) {
            return null;
        }

        return $digits;
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = $this->string($value);
        if (! $value) {
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

    /** @param array<string,mixed> $raw */
    private function sourceKey(int $unitId, string $academicYear, ?string $nik, ?string $birthDate, ?string $nisn, ?string $nipd, ?string $fullName, string $sourceSchool, array $raw): string
    {
        if ($nik && $birthDate) {
            return hash('sha256', 'match|'.$unitId.'|'.$academicYear.'|'.$nik.'|'.$birthDate);
        }

        $identity = implode('|', array_filter([$nisn, $nipd, $fullName, $sourceSchool], fn (?string $value): bool => filled($value)));
        if ($identity === '') {
            $identity = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'empty';
        }

        return hash('sha256', 'fallback|'.$unitId.'|'.$academicYear.'|'.$identity);
    }
}
