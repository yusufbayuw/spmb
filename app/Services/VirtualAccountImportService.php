<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\VirtualAccountBatch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

class VirtualAccountImportService
{
    public function __construct(private readonly RegistrationWorkflowService $workflow) {}

    public function importFile(string $storedPath, User $staff): array
    {
        $extension = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            return $this->importXlsx($storedPath, $staff);
        }

        return $this->importDelimitedFile($storedPath, $staff);
    }

    public function importCsv(string $storedPath, array $options, User $staff): array
    {
        return $this->importFile($storedPath, $staff);
    }

    private function importXlsx(string $storedPath, User $staff): array
    {
        $absolutePath = Storage::disk('local')->path($storedPath);

        if (! is_file($absolutePath)) {
            throw ValidationException::withMessages(['file' => 'File XLSX pool VA tidak ditemukan.']);
        }

        $temporaryPath = 'imports/virtual-accounts/'.Str::uuid().'.csv';
        Storage::disk('local')->makeDirectory('imports/virtual-accounts');
        $temporaryAbsolutePath = Storage::disk('local')->path($temporaryPath);
        $handle = fopen($temporaryAbsolutePath, 'wb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'File XLSX tidak dapat diproses.']);
        }

        $reader = new Reader();

        try {
            $reader->open($absolutePath);
            $hasSheet = false;

            foreach ($reader->getSheetIterator() as $sheet) {
                $hasSheet = true;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(
                        fn ($cell) => $cell->getValue(),
                        $row->getCells(),
                    );

                    fputcsv($handle, $values);
                }

                break;
            }

            if (! $hasSheet) {
                throw ValidationException::withMessages(['file' => 'File XLSX tidak memiliki worksheet yang dapat dibaca.']);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'file' => 'File XLSX tidak valid atau tidak dapat dibaca: '.$exception->getMessage(),
            ]);
        } finally {
            $reader->close();
            fclose($handle);
            Storage::disk('local')->delete($storedPath);
        }

        return $this->importDelimitedFile($temporaryPath, $staff, basename($storedPath));
    }

    private function importDelimitedFile(string $storedPath, User $staff, ?string $batchFilename = null): array
    {
        $absolutePath = Storage::disk('local')->path($storedPath);
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'File pool VA tidak dapat dibaca.']);
        }

        $batch = null;
        $total = 0;
        $imported = 0;
        $failed = 0;
        $errors = [];
        $seen = [];
        $touchedUnits = [];

        $staffUnit = null;
        if ($staff->isTU()) {
            $staffUnit = Unit::query()->where('is_active', true)->find($staff->unit_id);

            if (! $staffUnit) {
                throw ValidationException::withMessages([
                    'file' => 'Akun TU belum memiliki unit aktif. Hubungkan akun TU dengan unit sekolah terlebih dahulu.',
                ]);
            }
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false || trim($firstLine) === '') {
                throw ValidationException::withMessages(['file' => 'File pool VA kosong.']);
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            $firstRow = fgetcsv($handle, 0, $delimiter, '"', '');
            if ($firstRow === false) {
                throw ValidationException::withMessages(['file' => 'File pool VA tidak dapat dibaca.']);
            }

            $normalized = array_map(fn ($value) => $this->normalizeHeader((string) $value), $firstRow);
            $headerKeys = [
                'va_number', 'va', 'virtual_account',
                'bank',
                'unit', 'unit_code', 'kode_unit', 'unit_sekolah',
            ];
            $hasHeader = count(array_intersect($normalized, $headerKeys)) >= 2;
            $headerMap = $hasHeader ? array_flip($normalized) : null;

            $batch = VirtualAccountBatch::create([
                'filename' => $batchFilename ?? basename($storedPath),
                'imported_by' => $staff->id,
                'imported_at' => now(),
            ]);

            $process = function (array $row, int $rowNumber) use (
                &$total, &$imported, &$failed, &$errors, &$seen, &$touchedUnits,
                $headerMap, $batch, $staff, $staffUnit
            ): void {
                $total++;

                try {
                    $vaNumber = $this->cleanCell($this->valueFromRow($row, $headerMap, ['va_number', 'va', 'virtual_account'], 0));
                    $bank = strtoupper($this->cleanCell($this->valueFromRow($row, $headerMap, ['bank'], 1)));
                    $unitValue = $this->cleanCell($this->valueFromRow($row, $headerMap, ['unit', 'unit_code', 'kode_unit', 'unit_sekolah'], 2));

                    if ($vaNumber === '' || mb_strlen($vaNumber) > 50) {
                        throw new RuntimeException('Nomor VA kosong atau lebih dari 50 karakter.');
                    }

                    if ($bank === '' || mb_strlen($bank) > 50) {
                        throw new RuntimeException('Bank kosong atau lebih dari 50 karakter.');
                    }

                    if ($staff->isTU()) {
                        $unit = $staffUnit;

                        if ($unitValue !== '') {
                            $rowUnit = $this->resolveUnit($unitValue);

                            if (! $rowUnit || (int) $rowUnit->id !== (int) $staffUnit->id) {
                                throw new RuntimeException('Unit pada baris tidak sesuai dengan unit akun TU.');
                            }
                        }
                    } else {
                        if ($unitValue === '') {
                            throw new RuntimeException('Unit kosong. Super admin harus menyertakan unit pada setiap baris.');
                        }

                        $unit = $this->resolveUnit($unitValue);
                        if (! $unit) {
                            throw new RuntimeException("Unit '{$unitValue}' tidak ditemukan atau tidak aktif.");
                        }
                    }

                    $duplicateKey = $bank.'|'.$vaNumber;
                    if (isset($seen[$duplicateKey])) {
                        throw new RuntimeException('Nomor VA duplikat di dalam file untuk bank yang sama.');
                    }
                    $seen[$duplicateKey] = true;

                    if (VirtualAccount::query()->where('bank', $bank)->where('va_number', $vaNumber)->exists()) {
                        throw new RuntimeException('Nomor VA sudah ada di sistem untuk bank tersebut.');
                    }

                    VirtualAccount::create([
                        'batch_id' => $batch->id,
                        'unit_id' => $unit->id,
                        'bank' => $bank,
                        'va_number' => $vaNumber,
                        'status' => 'available',
                    ]);

                    $touchedUnits[$unit->id] = $unit;
                    $imported++;
                } catch (QueryException) {
                    $failed++;
                    if (count($errors) < 100) {
                        $errors[] = "Baris {$rowNumber}: nomor VA duplikat atau tidak valid.";
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    if (count($errors) < 100) {
                        $errors[] = "Baris {$rowNumber}: {$exception->getMessage()}";
                    }
                }
            };

            $rowNumber = 1;
            if (! $hasHeader && ! $this->rowIsEmpty($firstRow)) {
                $process($firstRow, $rowNumber);
            }

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $rowNumber++;
                if ($this->rowIsEmpty($row)) {
                    continue;
                }
                $process($row, $rowNumber);
            }
        } finally {
            fclose($handle);
            Storage::disk('local')->delete($storedPath);
        }

        if (! $batch) {
            throw ValidationException::withMessages(['file' => 'File pool VA tidak menghasilkan batch import.']);
        }

        $batch->update([
            'total_rows' => $total,
            'imported_rows' => $imported,
            'failed_rows' => $failed,
            'errors' => $errors ?: null,
        ]);

        $assigned = 0;
        foreach ($touchedUnits as $unit) {
            $assigned += $this->workflow->assignWaitingRegistrationsForUnit($unit, $staff);
        }

        return compact('batch', 'total', 'imported', 'failed', 'assigned');
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [
            '|' => substr_count($line, '|'),
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($counts);
        $delimiter = array_key_first($counts);

        return ($counts[$delimiter] ?? 0) > 0 ? $delimiter : '|';
    }

    private function normalizeHeader(string $value): string
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);

        return strtolower(trim(str_replace([' ', '-'], '_', $value)));
    }

    private function cleanCell(mixed $value): string
    {
        return trim((string) $value);
    }

    private function valueFromRow(array $row, ?array $headerMap, array $keys, int $fallbackIndex): mixed
    {
        if ($headerMap !== null) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $headerMap)) {
                    return $row[$headerMap[$key]] ?? null;
                }
            }

            return null;
        }

        return $row[$fallbackIndex] ?? null;
    }

    private function resolveUnit(string $value): ?Unit
    {
        $needle = mb_strtolower(trim($value));

        return Unit::query()
            ->where('is_active', true)
            ->where(function ($query) use ($needle): void {
                $query->whereRaw('LOWER(code) = ?', [$needle])
                    ->orWhereRaw('LOWER(name) = ?', [$needle]);
            })
            ->first();
    }

    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value) => blank($value));
    }
}
