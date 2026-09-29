<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    use HasPublicUuid;

    public const TYPES = [
        'terms_of_service' => 'Ketentuan Penggunaan',
        'privacy_policy' => 'Kebijakan Privasi',
        'marketing_consent' => 'Informasi & Promosi',
    ];

    protected $fillable = [
        'user_id',
        'account_consent_policy_id',
        'consent_type',
        'title_snapshot',
        'content_snapshot',
        'confirmation_snapshot',
        'content_hash',
        'accepted_at',
        'ip_address',
        'user_agent',
        'withdrawn_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(AccountConsentPolicy::class, 'account_consent_policy_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->consent_type] ?? $this->consent_type;
    }
}
