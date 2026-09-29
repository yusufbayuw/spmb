<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationConsent extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'user_id',
        'unit_id',
        'registration_opening_id',
        'unit_configuration_id',
        'registration_id',
        'title_snapshot',
        'content_snapshot',
        'confirmation_snapshot',
        'content_hash',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(RegistrationOpening::class, 'registration_opening_id');
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(UnitConfiguration::class, 'unit_configuration_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
