<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * An explicit, single-registration, privileged permanent purge.
 *
 * This is deliberately separate from Eloquent's general delete policies and
 * from the development-only spmb:reset-operational command. It never deletes
 * shared applicant accounts, configuration, quota, pool VA or audit history.
 */
class RegistrationPurgeService
{
    /** @var array<string, string> Child-first allowlist, kept in sync with migrations. */
    private const CHILD_TABLES = [
        'continuation_registration_links' => 'Tautan calon siswa lanjutan',
        'registration_consents' => 'Persetujuan pendaftaran',
        'registration_achievements' => 'Prestasi dan sertifikat',
        'registration_academic_scores' => 'Nilai akademik',
        're_registration_items' => 'Dokumen dan jawaban daftar ulang',
        'admission_offers' => 'Penawaran penerimaan',
        'announcements' => 'Pengumuman',
        'admission_test_results' => 'Hasil tes',
        'test_bookings' => 'Pemesanan jadwal tes',
        'selections' => 'Keputusan seleksi',
        'payment_receipts' => 'Kuitansi pembayaran',
        'payments' => 'Pembayaran dan bukti transfer',
        'documents' => 'Dokumen dan foto peserta',
        'parent_infos' => 'Informasi orang tua',
    ];

    private const STORAGE_FOLDERS = ['documents', 'payments', 're-registration'];

    public function canPurge(?User $actor, Registration $registration): bool
    {
        if (! $actor?->is_active) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        if (! $actor->isAdminUnit()
            || ! $actor->unit_id
            || (int) $actor->unit_id !== (int) $registration->unit_id) {
            return false;
        }

        // Always read the current setting, never an eager-loaded/stale Unit relation.
        return (bool) \App\Models\Unit::query()
            ->whereKey($registration->unit_id)
            ->value('allow_admin_unit_registration_purge');
    }

    public function authorize(User $actor, Registration $registration): void
    {
        abort_unless($this->canPurge($actor, $registration), 403);

        app(CertificationAccessService::class)
            ->assertSensitiveOperation($actor, 'menghapus permanen data pendaftaran');
    }

    /**
     * @return array{registration_id:int,uuid:string,unit_id:int,counts:array<string,array{label:string,count:int}>,virtual_accounts:int,audit_records:int,files:list<array{disk:string,path:string}>,fingerprint:string}
     */
    public function preview(Registration $registration, User $actor): array
    {
        $this->authorize($actor, $registration);
        $this->assertSafeSchema();

        $id = (int) $registration->getKey();
        $paymentIds = DB::table('payments')->where('registration_id', $id)->pluck('id')->all();
        $counts = [];

        foreach (self::CHILD_TABLES as $table => $label) {
            $query = DB::table($table);

            if ($table === 'payment_receipts') {
                $query->whereIn('payment_id', $paymentIds);
            } else {
                $query->where('registration_id', $id);
            }

            $counts[$table] = [
                'label' => $label,
                'count' => $query->count(),
            ];
        }

        $virtualAccounts = DB::table('virtual_accounts')
            ->where('registration_id', $id)
            ->get(['id', 'status']);
        $files = $this->ownedFiles($id);
        $auditRecords = DB::table('audit_logs')->where('registration_id', $id)->count();

        $data = [
            'registration_id' => $id,
            'uuid' => (string) $registration->uuid,
            'unit_id' => (int) $registration->unit_id,
            'counts' => $counts,
            'virtual_accounts' => $virtualAccounts->count(),
            'audit_records' => $auditRecords,
            'files' => $files,
        ];

        // Binding to the current file/dependency snapshot prevents stale
        // approval if the underlying application record changes.
        $data['fingerprint'] = hash('sha256', json_encode([
            $data,
            'registration_updated_at' => $registration->updated_at?->toJSON(),
            'va_states' => $virtualAccounts->toArray(),
        ], JSON_THROW_ON_ERROR));

        return $data;
    }

    /**
     * @return array{cleanup_pending:bool,manifest:string}
     */
    public function purge(
        Registration $record,
        User $actor,
        string $fingerprint,
        string $confirmation,
        string $reason,
    ): array {
        $this->authorize($actor, $record);

        if (! hash_equals('HAPUS-'.(string) $record->uuid, trim($confirmation))) {
            throw ValidationException::withMessages([
                'confirmation' => 'Ketik kode HAPUS beserta UUID pendaftaran secara persis.',
            ]);
        }

        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penghapusan wajib diisi minimal 10 karakter.',
            ]);
        }

        $manifestPath = 'purge-manifests/'.Str::uuid().'.json';
        $prepared = false;

        try {
            DB::transaction(function () use ($record, $actor, $fingerprint, $manifestPath, $reason, &$prepared): void {
                $registration = Registration::query()
                    ->whereKey($record->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                // Repeat authorization, including the Super Admin's current toggle,
                // after obtaining the lock.
                $snapshot = $this->preview($registration, $actor);

                if (! hash_equals($snapshot['fingerprint'], $fingerprint)) {
                    throw ValidationException::withMessages([
                        'confirmation' => 'Data berubah setelah pratinjau. Tutup dialog dan periksa dampaknya kembali.',
                    ]);
                }

                $prepared = $this->writeManifest($manifestPath, [
                    'status' => 'prepared',
                    'registration_id' => $snapshot['registration_id'],
                    'unit_id' => $snapshot['unit_id'],
                    'files' => $snapshot['files'],
                    'created_at' => now()->toIso8601String(),
                ]);

                if (! $prepared) {
                    throw new RuntimeException('Manifest pemulihan berkas tidak dapat disimpan; penghapusan dibatalkan.');
                }

                $id = $snapshot['registration_id'];
                $paymentIds = DB::table('payments')
                    ->where('registration_id', $id)
                    ->pluck('id')
                    ->all();

                // VA pool is shared and never deleted or made available again.
                DB::table('virtual_accounts')->where('registration_id', $id)->update([
                    'registration_id' => null,
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

                foreach (array_keys(self::CHILD_TABLES) as $table) {
                    $query = DB::table($table);
                    $table === 'payment_receipts'
                        ? $query->whereIn('payment_id', $paymentIds)->delete()
                        : $query->where('registration_id', $id)->delete();
                }

                // The global Eloquent deleted observer writes audit_logs with
                // an FK to this now-missing registration. Use a targeted SQL
                // delete and record the privileged purge explicitly below.
                DB::table('registrations')->where('id', $id)->delete();

                // Historic audit rows remain immutable and their foreign key
                // becomes NULL. Store only non-PII identifiers and counts.
                app(AuditTrail::class)->record(
                    event: 'registration.permanently_purged',
                    metadata: [
                        'deleted_registration_id' => $id,
                        'deleted_registration_uuid' => $snapshot['uuid'],
                        'dependent_counts' => array_map(
                            fn (array $entry): int => $entry['count'],
                            $snapshot['counts'],
                        ),
                        'virtual_accounts_cancelled' => $snapshot['virtual_accounts'],
                        'private_files_to_delete' => count($snapshot['files']),
                        'reason_sha256' => hash('sha256', trim($reason)),
                    ],
                    actor: $actor,
                    unitId: $snapshot['unit_id'],
                    description: 'Pendaftaran beserta relasi khusus peserta dihapus permanen (akun bersama dan histori audit dipertahankan).',
                );
            }, 3);
        } catch (Throwable $e) {
            if ($prepared) {
                Storage::disk('local')->delete($manifestPath);
            }

            throw $e;
        }

        $pending = $this->cleanupFilesFromManifest($manifestPath);

        return ['cleanup_pending' => $pending, 'manifest' => $manifestPath];
    }

    /**
     * Clean up private/public legacy files only from trusted registration
     * subdirectories; never scan or delete the shared pre-registration files.
     *
     * @return list<array{disk:string,path:string}>
     */
    private function ownedFiles(int $registrationId): array
    {
        $files = [];

        foreach ([ApplicantFileStorage::PRIVATE_DISK, ApplicantFileStorage::LEGACY_PUBLIC_DISK] as $disk) {
            foreach (self::STORAGE_FOLDERS as $folder) {
                $directory = $folder.'/'.$registrationId;

                foreach (Storage::disk($disk)->allFiles($directory) as $path) {
                    if (! str_starts_with($path, $directory.'/')
                        || str_contains($path, '..')
                        || str_contains($path, '\\')) {
                        continue;
                    }

                    $files[$disk.':'.$path] = ['disk' => $disk, 'path' => $path];
                }
            }
        }

        ksort($files);

        return array_values($files);
    }

    /**
     * Never silently cascade into a new table whose implications have not
     * been reviewed and displayed in the confirmation preview.
     */
    private function assertSafeSchema(): void
    {
        $deletedTables = [...array_keys(self::CHILD_TABLES), 'registrations'];
        $supportedTables = [...$deletedTables, 'virtual_accounts', 'audit_logs'];

        foreach (Schema::getTables() as $tableMetadata) {
            $table = $tableMetadata['name'] ?? null;

            if (! is_string($table)) {
                continue;
            }

            foreach (Schema::getForeignKeys($table) as $fk) {
                $referenced = $fk['foreign_table'] ?? null;

                if (in_array($referenced, $deletedTables, true)
                    && ! in_array($table, $supportedTables, true)) {
                    throw new RuntimeException(
                        "Tabel {$table} memiliki relasi ke {$referenced} yang belum didukung penghapusan permanen. Operasi diblokir.",
                    );
                }
            }
        }
    }

    private function writeManifest(string $path, array $manifest): bool
    {
        return Storage::disk('local')->put($path, json_encode($manifest, JSON_THROW_ON_ERROR)) !== false;
    }

    /**
     * Public for the privileged CLI repair command, not an HTTP endpoint.
     * Returns true when any file deletion still needs retry.
     */
    public function cleanupFilesFromManifest(string $path): bool
    {
        if (! preg_match('~^purge-manifests/[0-9a-f-]{36}\.json$~', $path)) {
            throw new RuntimeException('Nama manifest penghapusan tidak sah.');
        }

        $disk = Storage::disk('local');
        $data = json_decode((string) $disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        $registrationId = (int) ($data['registration_id'] ?? 0);

        if ($registrationId <= 0 || ! in_array($data['status'] ?? '', ['prepared', 'pending', 'complete'], true)) {
            throw new RuntimeException('Manifest penghapusan tidak sah.');
        }

        // If a process crashed after writing the manifest but before database
        // commit, its files must remain untouched.
        if (DB::table('registrations')->where('id', $registrationId)->exists()) {
            throw new RuntimeException('Pendaftaran masih ada; pembersihan berkas ditolak.');
        }

        if (($data['status'] ?? '') === 'complete') {
            return false;
        }

        $pending = [];

        foreach ($data['files'] ?? [] as $file) {
            $targetDisk = $file['disk'] ?? '';
            $pathName = $file['path'] ?? '';

            if (! in_array($targetDisk, [ApplicantFileStorage::PRIVATE_DISK, ApplicantFileStorage::LEGACY_PUBLIC_DISK], true)
                || ! is_string($pathName)
                || ! $this->isOwnedFile($pathName, $registrationId)) {
                // Fail closed: never delete an unexpected filesystem path.
                throw new RuntimeException('Manifest berisi path yang tidak diizinkan.');
            }

            try {
                $storage = Storage::disk($targetDisk);
                if ($storage->exists($pathName)) {
                    $storage->delete($pathName);
                }

                if ($storage->exists($pathName)) {
                    $pending[] = $file;
                }
            } catch (Throwable) {
                $pending[] = $file;
            }
        }

        $data['files'] = $pending;
        $data['status'] = $pending ? 'pending' : 'complete';
        $data['processed_at'] = now()->toIso8601String();

        if (! $this->writeManifest($path, $data)) {
            throw new RuntimeException('Gagal menyimpan status pembersihan berkas.');
        }

        return (bool) $pending;
    }

    private function isOwnedFile(string $path, int $registrationId): bool
    {
        if (str_contains($path, '..') || str_contains($path, '\\')
            || preg_match('/[\x00-\x1f]/', $path)) {
            return false;
        }

        foreach (self::STORAGE_FOLDERS as $folder) {
            if (str_starts_with($path, $folder.'/'.$registrationId.'/')) {
                return true;
            }
        }

        return false;
    }
}
