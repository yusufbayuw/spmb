<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Registration;
use App\Models\RegistrationOpening;
use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    /** @return list<string> */
    public function registrationBlockers(Registration $registration): array
    {
        $blockers = [];

        foreach ([
            'test_bookings' => 'booking tes',
            'admission_test_results' => 'hasil tes',
            'selections' => 'seleksi',
            'announcements' => 'pengumuman',
            'admission_offers' => 'penawaran penerimaan',
            're_registration_items' => 'daftar ulang',
        ] as $table => $label) {
            if (DB::table($table)->where('registration_id', $registration->id)->exists()) {
                $blockers[] = 'Masih ada '.$label.'.';
            }
        }

        if (filled($registration->payment_verified_at)) {
            $blockers[] = 'Pembayaran sudah diverifikasi.';
        }

        if (filled($registration->accepted_at) || filled($registration->enrolled_at)
            || filled($registration->re_registration_completed_at)
            || in_array($registration->status, ['accepted', 'rejected', 'waiting_list', 'confirmed', 'enrolled'], true)
            || in_array($registration->current_stage, [
                'selection', 'announcement', 'waiting_list', 'admission_offer',
                're_registration', 'enrollment', 'completed',
            ], true)) {
            $blockers[] = 'Pendaftaran sudah masuk tahap keputusan resmi atau daftar ulang.';
        }

        if ($registration->documents()->where(function ($query): void {
            $query->where('type', 'payment_proof')->orWhere('requirement_key', 'payment_proof');
        })->exists()) {
            $blockers[] = 'Bukti transfer masih tersimpan sebagai dokumen.';
        }

        $payments = Payment::query()->where('registration_id', $registration->id)->get();

        if ($payments->isNotEmpty()
            && DB::table('payment_receipts')->whereIn('payment_id', $payments->pluck('id'))->exists()) {
            $blockers[] = 'Kuitansi pembayaran sudah diterbitkan.';
        }

        foreach ($payments as $payment) {
            if ($payment->status !== 'pending'
                || filled($payment->payment_date)
                || filled($payment->verified_at)
                || filled($payment->verified_by)
                || filled($payment->proof_path)
                || filled($payment->proof_uploaded_at)
                || filled($payment->proof_sha256)
                || filled($payment->proof_original_name)) {
                $blockers[] = 'Pembayaran memiliki transaksi atau bukti yang harus dipertahankan.';
                break;
            }
        }

        foreach (VirtualAccount::query()->where('registration_id', $registration->id)->get() as $va) {
            if (! in_array($va->status, ['available', 'assigned', 'expired', 'cancelled'], true)) {
                $blockers[] = 'Status VA tidak aman untuk dilepaskan.';
                break;
            }

            if (Payment::query()->where('virtual_account_id', $va->id)
                ->where(function ($query) use ($registration): void {
                    $query->where('registration_id', '!=', $registration->id)
                        ->orWhereNull('registration_id');
                })->exists()) {
                $blockers[] = 'VA masih direferensikan pembayaran lain.';
                break;
            }
        }

        return array_values(array_unique($blockers));
    }

    public function registrationImpact(Registration $registration): string
    {
        $counts = [
            'dokumen' => $registration->documents()->count(),
            'data orang tua' => $registration->parentInfo()->count(),
            'nilai' => $registration->academicScores()->count(),
            'prestasi' => $registration->achievements()->count(),
            'persetujuan' => DB::table('registration_consents')->where('registration_id', $registration->id)->count(),
            'pembayaran pending' => $registration->payments()->where('status', 'pending')->count(),
            'VA tertaut' => VirtualAccount::query()->where('registration_id', $registration->id)->count(),
        ];

        $message = 'Relasi: '.collect($counts)
            ->map(fn (int $count, string $label): string => "{$label}: {$count}")
            ->implode('; ')
            .'. Tagihan pending tanpa bukti dihapus otomatis, VA tertaut ditutup dan tidak digunakan ulang. '
            .'Akun login, konfigurasi unit, dan jejak audit dipertahankan.';

        $blockers = $this->registrationBlockers($registration);

        return $message.($blockers === []
            ? ' Status: dapat dihapus setelah konfirmasi.'
            : ' BLOKIR: '.implode(' ', $blockers));
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

            // Lock dependencies and recheck the same blockers shown in the modal.
            $payments = Payment::query()->where('registration_id', $locked->id)->lockForUpdate()->get();
            $accounts = VirtualAccount::query()->where('registration_id', $locked->id)->lockForUpdate()->get();

            $blockers = $this->registrationBlockers($locked);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['delete' => implode(' ', $blockers)]);
            }

            $paths = array_values(array_unique(array_filter([
                ...$locked->documents()->pluck('file_path')->all(),
                ...$locked->achievements()->pluck('certificate_path')->all(),
            ])));

            // At this point payments may only be untouched pending bills:
            // no transfers, proof uploads, verification or issued receipts.
            foreach ($payments as $payment) {
                app(AuditTrail::class)->record(
                    'payment.unused_pending_removed',
                    $payment,
                    metadata: ['reason' => trim($reason), 'registration_uuid' => $locked->uuid],
                    actor: $actor,
                    description: 'Tagihan pending tanpa transaksi dibersihkan saat penghapusan pendaftaran',
                );
            }

            if ($payments->isNotEmpty()) {
                DB::table('payments')->whereIn('id', $payments->pluck('id'))->delete();
            }

            // Retain allocated VA numbers and assignment history. Never reset
            // their status to available when their registration is deleted.
            foreach ($accounts as $account) {
                app(AuditTrail::class)->record(
                    'virtual_account.retired_after_registration_deletion',
                    $account,
                    metadata: ['reason' => trim($reason), 'registration_uuid' => $locked->uuid],
                    actor: $actor,
                    description: 'VA dibatalkan permanen setelah pendaftaran dihapus',
                );

                DB::table('virtual_accounts')->where('id', $account->id)->update([
                    'status' => 'cancelled',
                    'registration_id' => null,
                    'updated_at' => now(),
                ]);
            }

            // RESTRICT consent FK: remove the individual registration consent
            // only. The applicant's other consents and user account survive.
            DB::table('registration_consents')->where('registration_id', $locked->id)->delete();

            app(AuditTrail::class)->record(
                'registration.permanently_deleted',
                $locked,
                metadata: [
                    'reason' => trim($reason),
                    'registration_uuid' => $locked->uuid,
                    'removed_pending_payments' => $payments->count(),
                    'retired_virtual_accounts' => $accounts->count(),
                ],
                actor: $actor,
                description: 'Pendaftaran dihapus permanen oleh petugas berwenang',
            );

            // Dependent parent/score/document rows use FK cascade. Avoid a
            // second model observer audit pointing at the deleted record.
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

    /**
     * Exception path for records the Super Admin personally confirms are
     * synthetic. This does not change normal Hapus Total rules or unit staff
     * permissions. The attestation is an operator assertion, not evidence
     * that a bank transfer or issued receipt was fictional.
     */
    public function deleteTestRegistration(
        Registration $registration,
        User $actor,
        string $reason,
        string $confirmation,
        bool $confirmedTestData
    ): array {
        abort_unless($actor->is_active && $actor->isAdmin(), 403);

        if (! $confirmedTestData || $confirmation !== 'HAPUS DATA UJI' || mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages([
                'delete' => 'Khusus Super Admin: centang pernyataan data uji, isi alasan minimal 10 karakter, lalu ketik HAPUS DATA UJI.',
            ]);
        }

        // Abort if a newer application version adds a foreign-key dependency
        // that this privileged path has not explicitly reviewed.
        $this->assertKnownTestDeletionDependencies();

        $paths = DB::transaction(function () use ($registration, $actor, $reason): array {
            $locked = Registration::query()->lockForUpdate()->findOrFail($registration->id);
            $unit = Unit::query()->lockForUpdate()->findOrFail($locked->unit_id);
            abort_unless($this->allowed($actor, $unit, 'registration'), 403);

            $payments = Payment::query()->where('registration_id', $locked->id)
                ->lockForUpdate()->get();
            $accounts = VirtualAccount::query()->where('registration_id', $locked->id)
                ->lockForUpdate()->get();

            // An unrelated registration must never lose its payment or VA.
            $foreignAccountPayments = Payment::query()
                ->whereIn('virtual_account_id', $accounts->pluck('id'))
                ->where(function ($query) use ($locked): void {
                    $query->where('registration_id', '!=', $locked->id)
                        ->orWhereNull('registration_id');
                })->exists();
            if ($foreignAccountPayments || $accounts->contains(
                fn (VirtualAccount $account): bool => (int) $account->unit_id !== (int) $locked->unit_id
            )) {
                throw ValidationException::withMessages([
                    'delete' => 'Ada keterkaitan VA dengan transaksi atau unit lain. Penghapusan data uji ditolak.',
                ]);
            }

            $receiptRows = DB::table('payment_receipts')
                ->whereIn('payment_id', $payments->pluck('id'))->get();

            $attachmentPaths = [
                ...$locked->documents()->pluck('file_path')->all(),
                ...$locked->achievements()->pluck('certificate_path')->all(),
                ...$payments->pluck('proof_path')->all(),
                ...DB::table('re_registration_items')
                    ->where('registration_id', $locked->id)->pluck('file_path')->all(),
            ];
            $paths = array_values(array_unique(array_filter($attachmentPaths)));

            $this->assertSafeTestFilePaths($paths);

            // Persist non-PII evidence of what was removed. Raw names, NIK,
            // proof contents, credentials, and receipt details are NOT logged.
            $paymentSummary = $payments->map(fn (Payment $payment): array => [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'verified' => filled($payment->verified_at),
                'has_proof' => filled($payment->proof_path),
            ])->all();
            $receiptSummary = $receiptRows->map(fn ($receipt): array => [
                'receipt_id' => $receipt->id,
                'payment_id' => $receipt->payment_id,
                'number' => $receipt->number,
            ])->all();

            $relationCounts = [];
            foreach ([
                'test_bookings',
                'admission_test_results',
                'selections',
                'announcements',
                'admission_offers',
                're_registration_items',
                'registration_consents',
            ] as $table) {
                $relationCounts[$table] = DB::table($table)
                    ->where('registration_id', $locked->id)->count();
            }

            app(AuditTrail::class)->record(
                'registration.test_data_forced_deleted',
                $locked,
                metadata: [
                    'reason' => trim($reason),
                    'operator_attested_test_data' => true,
                    'registration_uuid' => $locked->uuid,
                    'unit_id' => $locked->unit_id,
                    'payment_summary' => $paymentSummary,
                    'receipt_summary' => $receiptSummary,
                    'related_counts' => $relationCounts,
                    'va_ids' => $accounts->pluck('id')->all(),
                ],
                actor: $actor,
                description: 'Super Admin menghapus pendaftaran yang dinyatakan sebagai data uji beserta relasi terkait',
            );

            // RESTRICT relationships are explicitly removed in dependency order.
            // The receipt Eloquent model is intentionally immutable; only this
            // Super-Admin-only, audited test-data cleanup uses query builder.
            DB::table('payment_receipts')
                ->whereIn('payment_id', $payments->pluck('id'))->delete();
            DB::table('payments')->where('registration_id', $locked->id)->delete();
            DB::table('test_bookings')->where('registration_id', $locked->id)->delete();
            DB::table('re_registration_items')->where('registration_id', $locked->id)->delete();
            DB::table('admission_offers')->where('registration_id', $locked->id)->delete();
            DB::table('registration_consents')->where('registration_id', $locked->id)->delete();

            foreach ($accounts as $account) {
                // A VA marked paid remains paid as historical evidence;
                // all other assigned VAs become cancelled, never available.
                DB::table('virtual_accounts')->where('id', $account->id)->update([
                    'registration_id' => null,
                    'status' => $account->status === 'paid' ? 'paid' : 'cancelled',
                    'updated_at' => now(),
                ]);
            }

            // Remaining registration child tables cascade or null on delete.
            // The applicant login account, unit settings, and audit stay intact.
            DB::table('registrations')->where('id', $locked->id)->delete();

            return $paths;
        }, 3);

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

    /**
     * Fail closed when future migrations add relations whose cascade/cleanup
     * semantics have not been explicitly reviewed for this operation.
     */
    private function assertKnownTestDeletionDependencies(): void
    {
        $deletedTables = [
            'registrations', 'payments', 'payment_receipts', 'test_bookings',
            're_registration_items', 'admission_offers', 'registration_consents',
            'documents', 'parent_infos', 'registration_achievements',
            'registration_academic_scores', 'admission_test_results',
            'selections', 'announcements', 'continuation_registration_links',
        ];
        $supportedReferencingTables = [
            ...$deletedTables, 'virtual_accounts', 'audit_logs',
            'mail_delivery_attempts', 'pending_applicant_email_changes',
        ];

        foreach (Schema::getTables() as $tableInfo) {
            $table = $tableInfo['name'] ?? null;

            if (! is_string($table)) {
                continue;
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (in_array($foreignKey['foreign_table'] ?? null, $deletedTables, true)
                    && ! in_array($table, $supportedReferencingTables, true)) {
                    throw ValidationException::withMessages([
                        'delete' => "Penghapusan diblokir: tabel {$table} memiliki relasi baru yang perlu ditinjau.",
                    ]);
                }
            }
        }
    }

    private function assertSafeTestFilePaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_string($path) || $path === ''
                || str_starts_with($path, '/')
                || str_contains($path, '..')
                || str_contains($path, '\\')
                || preg_match('/[\x00-\x1f]/', $path)) {
                throw ValidationException::withMessages([
                    'delete' => 'Ditemukan path berkas tidak aman; penghapusan dibatalkan.',
                ]);
            }
        }
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
