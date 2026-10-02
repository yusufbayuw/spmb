<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCertification extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'user_id', 'certification_program_id', 'certification_attempt_id',
        'recipient_name_snapshot', 'recipient_unit_snapshot', 'recipient_role_snapshot',
        'program_code_snapshot', 'program_name_snapshot', 'program_version_snapshot',
        'certificate_number', 'verification_code', 'score', 'theory_score', 'practical_score',
        'issued_at', 'expires_at', 'status',
        'revoked_at', 'revoked_by_user_id', 'revocation_reason', 'revocation_metadata',
        'expiry_reminders_sent', 'artifact_path', 'artifact_sha256', 'artifact_signature',
        'artifact_signature_algorithm', 'artifact_generated_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'theory_score' => 'decimal:2',
        'practical_score' => 'decimal:2',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'revocation_metadata' => 'array',
        'expiry_reminders_sent' => 'array',
        'artifact_generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(CertificationProgram::class, 'certification_program_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CertificationAttempt::class, 'certification_attempt_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    public function effectiveStatus(): string
    {
        if ($this->status === 'revoked') {
            return 'revoked';
        }

        if ($this->expires_at?->isPast()) {
            return 'expired';
        }

        return $this->status;
    }

    public function isValid(): bool
    {
        return $this->effectiveStatus() === 'active';
    }

    public function recipientName(): string
    {
        return $this->recipient_name_snapshot ?: $this->user?->name ?: '-';
    }

    public function recipientUnit(): string
    {
        return $this->recipient_unit_snapshot ?: $this->user?->unit?->name ?: 'Admin Pusat';
    }

    public function recipientRole(): string
    {
        return $this->recipient_role_snapshot ?: $this->program?->roleLabel() ?: '-';
    }

    public function programName(): string
    {
        return $this->program_name_snapshot ?: $this->program?->name ?: '-';
    }

    public function programCode(): string
    {
        return $this->program_code_snapshot ?: $this->program?->code ?: '-';
    }

    public function programVersion(): string
    {
        return $this->program_version_snapshot ?: $this->program?->version ?: '-';
    }
}
