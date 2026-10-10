<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ControlledDeletionService
{
    private const FLAGS = [
        'registration' => 'allow_admin_unit_registration_deletion',
        'virtual_account' => 'allow_admin_unit_va_deletion',
        'opening' => 'allow_admin_unit_opening_deletion',
    ];

    public function allowed(User $actor, Unit $unit, string $kind): bool
    {
        if (! $actor->is_active || ! isset(self::FLAGS[$kind])) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        return $actor->isAdminUnit()
            && (int) $actor->unit_id === (int) $unit->id
            && $unit->isOperational()
            && (bool) $unit->getAttribute(self::FLAGS[$kind]);
    }

    public function registrationImpact(Registration $registration): string
    {
        $counts = [
            'dokumen' => $registration->documents()->count(),
            'data orang tua' => $registration->parentInfo()->count(),
            'nilai' => $registration->academicScores()->count(),
            'prestasi' => $registration->achievements()->count(),
            'persetujuan' => DB::table('registration_consents')->where('registration_id', $registration->id)->count(),
            'pembayaran' => $registration->payments()->count(),
            'VA tertaut' => VirtualAccount::query()->where('registration_id', $registration->id)->count(),
        ];

        return 'Hapus permanen data pendaftaran dan seluruh data turunannya yang diperbolehkan. '
            .collect($counts)->map(fn (int $count, string $label): string => "{$label}: {$count}")->implode('; ')
            .'. Akun login pendaftar dan konfigurasi unit tidak ikut dihapus. '
            .'Jika ada pembayaran, VA tertaut, tes/seleksi, atau hasil penerimaan, proses akan ditolak.';
    }

    public function vaImpact(VirtualAccount $va): string
    {
        return "Hapus permanen VA {$va->va_number}. Hanya VA yang belum pernah ditugaskan, tanpa pembayaran, "
            .'dan berstatus Tersedia atau Dibatalkan yang boleh dihapus.';
    }

    public function openingImpact(RegistrationOpening $opening): string
    {
        return 'Hapus permanen pembukaan '.$opening->label().'. '
            .'Pendaftar: '.$opening->registrations()->count()
            .'; kuota: '.$opening->admissionQuotas()->count()
            .'; batch seleksi: '.$opening->selectionBatches()->count()
            .'; persetujuan: '.DB::table('registration_consents')->where('registration_opening_id', $opening->id)->count()
            .'. Semua hitungan harus nol.';
    }

    public function deleteRegistration(Registration $registration, User $actor, string $reason, string $confirmation): array
    {
        $this->confirm($reason, $confirmation);
        $paths = DB::transaction(function () use ($registration, $actor, $reason): array {
            $locked = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            $unit = Unit::query()->lockForUpdate()->findOrFail($locked->unit_id);
            abort_unless($this->allowed($actor, $unit, 'registration'), 403);

            $blockedTables = [
                'payments', 'test_bookings', 'admission_test_results', 'selections',
                'announcements', 'admission_offers', 're_registration_items',
            ];
            foreach ($blockedTables as $table) {
                if (DB::table($table)->where('registration_id', $locked->id)->exists()) {
                    throw ValidationException::withMessages([
                        'delete' => 'Pendaftaran memiliki relasi operasional/keuangan pada '.$table.'. Arsipkan atau batalkan, jangan hapus permanen.',
                    ]);
                }
            }

            if (VirtualAccount::query()->where('registration_id', $locked->id)->exists()
                || filled($locked->registration_number)
                || filled($locked->payment_verified_at)
                || filled($locked->applicant_card_issued_at)
                || filled($locked->enrolled_at)
                || filled($locked->accepted_at)) {
                throw ValidationException::withMessages([
                    'delete' => 'Pendaftaran sudah memiliki VA, nomor resmi, atau keputusan lanjutan. Hapus permanen ditolak.',
                ]);
            }

            $paths = array_values(array_unique(array_filter([
                ...$locked->documents()->pluck('file_path')->all(),
                ...$locked->achievements()->pluck('certificate_path')->all(),
            ])));

            // Consent has RESTRICT foreign keys: remove only the consent attached
            // to this registration, never other consents of the same applicant.
            DB::table('registration_consents')->where('registration_id', $locked->id)->delete();

            app(AuditTrail::class)->record(
                'registration.permanently_deleted',
                $locked,
                metadata: ['reason' => trim($reason), 'registration_uuid' => $locked->uuid],
                actor: $actor,
                description: 'Pendaftaran dihapus permanen oleh petugas berwenang',
            );

            // Other dependent records use FK cascades. RESTRICT references are
            // left intact and will roll back this entire transaction if present.
            // Delete via query builder: Eloquent's global deleted observer would
            // try to write a second audit record referencing a deleted parent.
            // The explicit audit event above survives via nullOnDelete FK.
            DB::table('registrations')->where('id', $locked->id)->delete();

            return $paths;
        });

        $failedPaths = [];
        foreach ($paths as $path) {
            try {
                app(ApplicantFileStorage::class)->delete($path);
            } catch (\Throwable $exception) {
                report($exception);
                $failedPaths[] = $path;
            }
        }

        return ['file_count' => count($paths), 'file_errors' => count($failedPaths)];
    }

    public function deleteVirtualAccount(VirtualAccount $va, User $actor, string $reason, string $confirmation): void
    {
        $this->confirm($reason, $confirmation);
        DB::transaction(function () use ($va, $actor, $reason): void {
            $locked = VirtualAccount::query()->lockForUpdate()->findOrFail($va->id);
            $unit = Unit::query()->lockForUpdate()->findOrFail($locked->unit_id);
            abort_unless($this->allowed($actor, $unit, 'virtual_account'), 403);

            if (! in_array($locked->status, ['available', 'cancelled'], true)
                || $locked->registration_id !== null
                || $locked->assigned_at !== null
                || $locked->payment()->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'Hanya VA yang belum ditugaskan, belum digunakan, dan tidak terkait pembayaran yang boleh dihapus.',
                ]);
            }

            app(AuditTrail::class)->record(
                'virtual_account.permanently_deleted',
                $locked,
                metadata: ['reason' => trim($reason), 'va_uuid' => $locked->uuid, 'bank' => $locked->bank],
                actor: $actor,
                description: 'VA kosong dihapus permanen oleh petugas berwenang',
            );
            DB::table($locked->getTable())->where('id', $locked->id)->delete();
        });
    }

    public function deleteOpening(RegistrationOpening $opening, User $actor, string $reason, string $confirmation): void
    {
        $this->confirm($reason, $confirmation);
        DB::transaction(function () use ($opening, $actor, $reason): void {
            $locked = RegistrationOpening::query()->lockForUpdate()->findOrFail($opening->id);
            $unit = Unit::query()->lockForUpdate()->findOrFail($locked->unit_id);
            abort_unless($this->allowed($actor, $unit, 'opening'), 403);

            if ($locked->registrations()->exists()
                || $locked->admissionQuotas()->exists()
                || $locked->selectionBatches()->exists()
                || DB::table('registration_consents')->where('registration_opening_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'delete' => 'Gelombang memiliki pendaftar, persetujuan, kuota, atau batch seleksi. Gunakan Arsipkan.',
                ]);
            }

            app(AuditTrail::class)->record(
                'registration_opening.permanently_deleted',
                $locked,
                metadata: ['reason' => trim($reason), 'opening_uuid' => $locked->uuid],
                actor: $actor,
                description: 'Gelombang kosong dihapus permanen oleh petugas berwenang',
            );
            DB::table($locked->getTable())->where('id', $locked->id)->delete();
        });
    }

    private function confirm(string $reason, string $confirmation): void
    {
        if (trim($reason) === '' || $confirmation !== 'HAPUS') {
            throw ValidationException::withMessages([
                'delete' => 'Isi alasan penghapusan dan ketik HAPUS sebagai konfirmasi.',
            ]);
        }
    }
}
