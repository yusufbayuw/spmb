<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class CertificationAccessService
{
    private const ROLE_PROGRAM = [
        'super_admin' => 'SCA',
        'admin_unit' => 'SCUA',
        'tu' => 'SCAO',
    ];

    private const SENSITIVE_KEYS = [
        'super_admin' => [
            'user', 'unit', 'registrationopening', 'selection', 'selectionbatch',
            'announcement', 'admissionquota', 'admissiontest',
        ],
        'admin_unit' => [
            'registrationopening', 'selection', 'selectionbatch', 'announcement',
            'admissionquota', 'admissiontest', 'unitconfiguration',
        ],
        'tu' => [
            'registration', 'document', 'payment', 'admissiontestresult',
            'reregistrationitem',
        ],
    ];

    public function requiredProgramCode(User $user): ?string
    {
        foreach (self::ROLE_PROGRAM as $role => $program) {
            if ($user->hasRole($role)) {
                return $program;
            }
        }

        return null;
    }

    public function certified(User $user): bool
    {
        $code = $this->requiredProgramCode($user);

        return ! $code || $user->hasValidCertification($code);
    }

    public function allowsResourceMutation(User $user, string $resourceKey, string $action): bool
    {
        $mode = app(TrainingGovernanceService::class)->enforcementMode();

        if (in_array($mode, ['off', 'warning'], true)) {
            return true;
        }

        if ($this->certified($user)) {
            return true;
        }

        if ($mode === 'full_role') {
            return false;
        }

        $role = $this->role($user);

        return ! in_array(
            strtolower($resourceKey),
            self::SENSITIVE_KEYS[$role] ?? [],
            true,
        );
    }

    public function assertSensitiveOperation(User $user, string $operation): void
    {
        $mode = app(TrainingGovernanceService::class)->enforcementMode();

        if (in_array($mode, ['off', 'warning'], true) || $this->certified($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'certification' => 'Sertifikasi role yang masih aktif diperlukan untuk '.$operation.'.',
        ]);
    }

    public function warning(User $user): ?string
    {
        if (app(TrainingGovernanceService::class)->enforcementMode() !== 'warning'
            || $this->certified($user)) {
            return null;
        }

        $code = $this->requiredProgramCode($user);

        return $code
            ? "Sertifikasi {$code} belum aktif. Operasi masih diizinkan karena enforcement berada pada mode Peringatan."
            : null;
    }

    private function role(User $user): ?string
    {
        foreach (array_keys(self::ROLE_PROGRAM) as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }
}
