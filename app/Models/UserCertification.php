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
        'certificate_number', 'verification_code', 'score', 'issued_at',
        'expires_at', 'status',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
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
}
