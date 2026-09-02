<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\VirtualAccountBatch;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class VirtualAccountImportService
{
    public function __construct(private readonly RegistrationWorkflowService $workflow) {}

    public function importCsv(string $storedPath, array $options, User $staff): array
    {
        $unit = Unit::query()->findOrFail($options['unit_id']);

        if ($staff->isTU() && (int) $staff->unit_id !== (int) $unit->id) {
            throw ValidationException::withMessages([
                'unit_id' => 'Petugas TU hanya dapat mengimpor VA untuk unitnya sendiri.',
            ]);
        }

        $bank = strtoupper(trim((string) $options['bank']));
        $defaultAmount = (float) $options['default_amount'];
        $defaultExpiresAt = filled($options['expires_at'] ?? null)
            ? Carbon::parse($options['expires_at'])
            : null;

        $absolutePath = Storage::disk('local')->path($storedPath);
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'File CSV tidak dapat dibaca.']);
        }

        $batch = VirtualAccountBatch::create([
            'unit_id' => $unit->id,
            'bank' => $bank,
            'filename' => basename($storedPath),
            'default_amount' => $defaultAmount,
            'expires_at' => $defaultExpiresAt,
            'imported_by' => $staff->id,
            'imported_at' => now(),
        ]);

        $total = 0;
        $imported = 0;
        $failed = 0;
        $errors = [];
        $seen = [];

        try {
            $firstRow = fgetcsv($handle);

            if ($firstRow === false) {
                throw ValidationException::withMessages(['file' => 'File CSV kosong.']);
            }

            $normalized = array_map(fn ($value) => $this->normalizeHeader((string) $value), $firstRow);
            $hasHeader = in_array('va_number', $normalized, true)
                || in_array('va', $normalized, true)
                || in_array('virtual_account', $normalized, true);
            $headerMap = $hasHeader ? array_flip($normalized) : null;

            $process = function (array $row, int $rowNumber) use (
                &$total, &$imported, &$failed, &$errors, &$seen,
                $headerMap, $batch, $unit, $bank, $defaultAmount, $defaultExpiresAt
            ): void {
                $total++;

                try {
                    $vaNumber = trim((string) $this->valueFromRow($row, $headerMap, ['va_number', 'va', 'virtual_account'], 0));
                    $amountRaw = $this->valueFromRow($row, $headerMap, ['amount', 'nominal'], 1);
                    $expiresRaw = $this->valueFromRow($row, $headerMap, ['expired_at', 'expires_at', 'expiry'], 2);

                    if ($vaNumber === '' || mb_strlen($vaNumber) > 50) {
                        throw new \RuntimeException('Nomor VA kosong atau lebih dari 50 karakter.');
                    }

                    $duplicateKey = $bank.'|'.$vaNumber;
                    if (isset($seen[$duplicateKey])) {
                        throw new \RuntimeException('Nomor VA duplikat di dalam file.');
                    }
                    $seen[$duplicateKey] = true;

                    $amount = filled($amountRaw) ? (float) str_replace([',', ' '], '', (string) $amountRaw) : $defaultAmount;
                    if ($amount <= 0) {
                        throw new \RuntimeException('Nominal VA harus lebih dari 0.');
                    }

                    $expiresAt = filled($expiresRaw) ? Carbon::parse((string) $expiresRaw) : $defaultExpiresAt;
                    if ($expiresAt?->isPast()) {
                        throw new \RuntimeException('Tanggal kedaluwarsa sudah lewat.');
                    }

                    if (VirtualAccount::query()->where('bank', $bank)->where('va_number', $vaNumber)->exists()) {
                        throw new \RuntimeException('Nomor VA sudah ada di sistem.');
                    }

                    VirtualAccount::create([
                        'batch_id' => $batch->id,
                        'unit_id' => $unit->id,
                        'bank' => $bank,
                        'va_number' => $vaNumber,
                        'amount' => $amount,
                        'status' => 'available',
                        'expired_at' => $expiresAt,
                    ]);

                    $imported++;
                } catch (QueryException $exception) {
                    $failed++;
                    if (count($errors) < 100) $errors[] = "Baris {$rowNumber}: nomor VA duplikat atau tidak valid.";
                } catch (Throwable $exception) {
                    $failed++;
                    if (count($errors) < 100) $errors[] = "Baris {$rowNumber}: {$exception->getMessage()}";
                }
            };

            $rowNumber = 1;
            if (! $hasHeader) {
                $process($firstRow, $rowNumber);
            }

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($this->rowIsEmpty($row)) continue;
                $process($row, $rowNumber);
            }
        } finally {
            fclose($handle);
            Storage::disk('local')->delete($storedPath);
        }

        $batch->update([
            'total_rows' => $total,
            'imported_rows' => $imported,
            'failed_rows' => $failed,
            'errors' => $errors ?: null,
        ]);

        $assigned = $this->workflow->assignWaitingRegistrationsForUnit($unit, $staff);

        return compact('batch', 'total', 'imported', 'failed', 'assigned');
    }

    private function normalizeHeader(string $value): string
    {
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        return strtolower(trim(str_replace([' ', '-'], '_', $value)));
    }

    private function valueFromRow(array $row, ?array $headerMap, array $keys, int $fallbackIndex): mixed
    {
        if ($headerMap !== null) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $headerMap)) return $row[$headerMap[$key]] ?? null;
            }

            return null;
        }

        return $row[$fallbackIndex] ?? null;
    }

    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value) => blank($value));
    }
}
