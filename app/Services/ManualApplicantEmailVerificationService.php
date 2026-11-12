<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ManualApplicantEmailVerificationService
{
    public const METHODS = [
        'in_person' => 'Pemeriksaan identitas secara tatap muka',
        'registered_phone' => 'Konfirmasi melalui nomor telepon yang sudah tercatat',
        'official_record' => 'Pencocokan dengan dokumen administrasi resmi',
    ];

    /**
     * A supervised exception to mailbox verification; never changes the
     * registration workflow, applicant data, payments or user permissions.
     *
     * The staff attestation and evidence reference must correspond to an
     * independently performed identity check, not just an unchecked box.
     *
     * @param  array<string, mixed>  $input
     */
    public function verify(User $actor, User $applicant, array $input): void
    {
        // Authorization must not rely on Filament action visibility.
        abort_unless($actor->is_active && $actor->isAdmin(), 403);

        $data = Validator::make($input, [
            'confirmed_email' => ['required', 'email:rfc', 'max:254'],
            'verification_method' => ['required', Rule::in(array_keys(self::METHODS))],
            'evidence_reference' => ['required', 'string', 'min:4', 'max:120'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'identity_checked' => ['required', 'accepted'],
        ])->validate();

        DB::transaction(function () use ($actor, $applicant, $data): void {
            $staff = User::query()->whereKey($actor->getKey())->firstOrFail();
            abort_unless($staff->is_active && $staff->isAdmin(), 403);

            $user = User::query()
                ->whereKey($applicant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $user->is_active
                || ! $user->hasRole('pendaftar')
                || $user->hasAnyRole(['super_admin', 'admin_unit', 'tu'])) {
                abort(403, 'Verifikasi manual hanya tersedia untuk akun pendaftar aktif.');
            }

            if ($user->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'confirmed_email' => 'Email akun ini sudah terverifikasi.',
                ]);
            }

            if (! hash_equals(
                mb_strtolower(trim((string) $user->email)),
                mb_strtolower(trim($data['confirmed_email'])),
            )) {
                throw ValidationException::withMessages([
                    'confirmed_email' => 'Email konfirmasi tidak sama dengan email akun.',
                ]);
            }

            if (! $user->markEmailAsVerified()) {
                throw new RuntimeException('Gagal menyimpan verifikasi email pendaftar.');
            }

            app(AuditTrail::class)->record(
                'applicant.email_verified_manually',
                $user,
                oldValues: ['email_verified' => false],
                newValues: ['email_verified' => true],
                metadata: [
                    'verification_method' => $data['verification_method'],
                    'evidence_reference' => trim($data['evidence_reference']),
                    'reason' => trim($data['reason']),
                    'identity_checked' => true,
                ],
                actor: $staff,
                description: 'Super Admin menandai email pendaftar terverifikasi setelah pemeriksaan identitas',
            );
        }, 5);
    }
}
