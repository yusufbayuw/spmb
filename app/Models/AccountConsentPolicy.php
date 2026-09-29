<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class AccountConsentPolicy extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'version',
        'status',
        'terms_title',
        'terms_content',
        'privacy_title',
        'privacy_content',
        'required_confirmation_text',
        'marketing_enabled',
        'marketing_text',
        'published_at',
    ];

    protected $casts = [
        'marketing_enabled' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $policy): void {
            if ($policy->getOriginal('status') === 'published') {
                throw ValidationException::withMessages([
                    'policy' => 'Versi kebijakan yang sudah dipublikasikan tidak dapat diubah. Buat versi baru.',
                ]);
            }
        });

        static::deleting(fn () => throw ValidationException::withMessages([
            'policy' => 'Riwayat kebijakan akun tidak dapat dihapus.',
        ]));
    }

    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }
}
