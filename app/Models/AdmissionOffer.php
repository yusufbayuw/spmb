<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AdmissionOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionOffer extends Model
{
    /** @use HasFactory<AdmissionOfferFactory> */
    use HasFactory;

    use HasPublicUuid;

    public const ACTIVE_STATUSES = ['offered', 'accepted'];

    protected $fillable = [
        'registration_id', 'admission_quota_id', 'status', 'offered_at', 'expires_at', 'accepted_at',
        'declined_at', 'expired_at', 'decline_reason', 'last_reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime', 'expires_at' => 'datetime', 'accepted_at' => 'datetime',
            'declined_at' => 'datetime', 'expired_at' => 'datetime', 'last_reminded_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function quota(): BelongsTo
    {
        return $this->belongsTo(AdmissionQuota::class, 'admission_quota_id');
    }
}
