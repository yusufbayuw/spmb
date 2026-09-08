<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class IndonesiaRegionImportService
{
    public const EXPECTED_TOTALS = [
        'provinces' => 38,
        'regencies' => 514,
        'districts' => 7285,
        'villages' => 83762,
    ];

    private const HEADERS = [
        'province_code',
        'province_name',
        'regency_code',
        'regency_name',
        'district_code',
        'district_name',
        'village_code',
        'village_name',
    ];

    /**
     * Import every CSV shard in a directory in lexical order.
     *
     * @return array{rows:int,provinces:int,regencies:int,districts:int,villages:int}
     */
    public function importDirectory(string $directory, int $chunkSize = 1000): array
    {
        if (! is_dir($directory) || ! is_readable($directory)) {
            throw ValidationException::withMessages([
                'file' => 'Direktori master wilayah tidak ditemukan atau tidak dapat dibaca.',
            ]);
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.csv') ?: [];
        sort($files, SORT_STRING);

        if ($files === []) {
            throw ValidationException::withMessages([
                'file' => 'Direktori master wilayah tidak memiliki file CSV.',
            ]);
        }

        $rows = 0;
        $result = ['rows' => 0, 'provinces' => 0, 'regencies' => 0, 'districts' => 0, 'villages' => 0];

        foreach ($files as $file) {
            $imported = $this->import($file, $chunkSize);
            $rows += $imported['rows'];
            $result = $imported;
        }

        $result['rows'] = $rows;

        return $result;
    }

    /**
     * @param array{rows:int,provinces:int,regencies:int,districts:int,villages:int} $result
     */
    public function assertComplete(array $result): void
    {
        foreach (self::EXPECTED_TOTALS as $key => $expected) {
            if (($result[$key] ?? null) !== $expected) {
                throw ValidationException::withMessages([
                    'file' => "Master wilayah belum lengkap: {$key} harus {$expected}, ditemukan ".($result[$key] ?? 0).'.',
                ]);
            }
        }
    }

    /**
     * @return array{rows:int,provinces:int,regencies:int,districts:int,villages:int}
     */
    public function import(string $path, int $chunkSize = 1000): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'file' => 'File master wilayah tidak ditemukan atau tidak dapat dibaca.',
            ]);
        }

        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $header = $this->normalizeHeader($file->fgetcsv());

        if ($header !== self::HEADERS) {
            throw ValidationException::withMessages([
                'file' => 'Header CSV harus: '.implode(',', self::HEADERS),
            ]);
        }

        $buffers = $this->emptyBuffers();
        $counts = ['rows' => 0, 'provinces' => 0, 'regencies' => 0, 'districts' => 0, 'villages' => 0];

        $line = 1;

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            $line++;

            if ($row === false || $row === [null]) {
                continue;
            }

            if (count($row) !== count(self::HEADERS)) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$line}: jumlah kolom tidak sesuai header master wilayah.",
                ]);
            }

            $data = array_combine(self::HEADERS, array_map(
                fn ($value): string => trim((string) $value),
                $row,
            ));

            foreach (['province_code', 'regency_code', 'district_code', 'village_code'] as $codeField) {
                $data[$codeField] = $this->normalizeCode($data[$codeField], $codeField);
            }

            $this->validateRow($data, $line);
            $now = now();

            $buffers['provinces'][$data['province_code']] = [
                'code' => $data['province_code'],
                'name' => $data['province_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $buffers['regencies'][$data['regency_code']] = [
                'code' => $data['regency_code'],
                'province_code' => $data['province_code'],
                'name' => $data['regency_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $buffers['districts'][$data['district_code']] = [
                'code' => $data['district_code'],
                'regency_code' => $data['regency_code'],
                'name' => $data['district_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $buffers['villages'][$data['village_code']] = [
                'code' => $data['village_code'],
                'district_code' => $data['district_code'],
                'name' => $data['village_name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $counts['rows']++;

            if (count($buffers['villages']) >= $chunkSize) {
                $this->flush($buffers);
                $buffers = $this->emptyBuffers();
            }
        }

        if ($buffers['villages'] !== []) {
            $this->flush($buffers);
        }

        $counts['provinces'] = DB::table('provinces')->count();
        $counts['regencies'] = DB::table('regencies')->count();
        $counts['districts'] = DB::table('districts')->count();
        $counts['villages'] = DB::table('villages')->count();

        return $counts;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $buffers
     */
    private function flush(array $buffers): void
    {
        DB::transaction(function () use ($buffers): void {
            foreach (['provinces', 'regencies', 'districts', 'villages'] as $table) {
                $rows = array_values($buffers[$table]);

                if ($rows === []) {
                    continue;
                }

                $updates = match ($table) {
                    'provinces' => ['name', 'updated_at'],
                    'regencies' => ['province_code', 'name', 'updated_at'],
                    'districts' => ['regency_code', 'name', 'updated_at'],
                    'villages' => ['district_code', 'name', 'updated_at'],
                };

                DB::table($table)->upsert($rows, ['code'], $updates);
            }
        }, 5);
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function emptyBuffers(): array
    {
        return [
            'provinces' => [],
            'regencies' => [],
            'districts' => [],
            'villages' => [],
        ];
    }

    /**
     * @param array<int, string|null>|false $header
     * @return array<int, string>
     */
    private function normalizeHeader(array|false $header): array
    {
        if ($header === false) {
            return [];
        }

        return array_map(
            fn ($value): string => strtolower(trim((string) $value)),
            $header,
        );
    }

    private function normalizeCode(string $code, string $field): string
    {
        $digits = str_replace('.', '', trim($code));

        return match ($field) {
            'province_code' => strlen($digits) === 2 ? $digits : $code,
            'regency_code' => strlen($digits) === 4
                ? substr($digits, 0, 2).'.'.substr($digits, 2, 2)
                : $code,
            'district_code' => strlen($digits) === 6
                ? substr($digits, 0, 2).'.'.substr($digits, 2, 2).'.'.substr($digits, 4, 2)
                : $code,
            'village_code' => strlen($digits) === 10
                ? substr($digits, 0, 2).'.'.substr($digits, 2, 2).'.'.substr($digits, 4, 2).'.'.substr($digits, 6, 4)
                : $code,
            default => $code,
        };
    }

    /**
     * @param array<string, string> $data
     */
    private function validateRow(array $data, int $line): void
    {
        $patterns = [
            'province_code' => '/^\d{2}$/',
            'regency_code' => '/^\d{2}\.\d{2}$/',
            'district_code' => '/^\d{2}\.\d{2}\.\d{2}$/',
            'village_code' => '/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/',
        ];

        foreach ($patterns as $field => $pattern) {
            if (! preg_match($pattern, $data[$field])) {
                throw ValidationException::withMessages([
                    'file' => "Baris {$line}: format {$field} tidak valid.",
                ]);
            }
        }

        foreach (['province_name', 'regency_name', 'district_name', 'village_name'] as $field) {
            if ($data[$field] === '') {
                throw ValidationException::withMessages([
                    'file' => "Baris {$line}: {$field} wajib diisi.",
                ]);
            }
        }

        if (! str_starts_with($data['regency_code'], $data['province_code'].'.')
            || ! str_starts_with($data['district_code'], $data['regency_code'].'.')
            || ! str_starts_with($data['village_code'], $data['district_code'].'.')) {
            throw ValidationException::withMessages([
                'file' => "Baris {$line}: hierarki kode wilayah tidak konsisten.",
            ]);
        }
    }
}
