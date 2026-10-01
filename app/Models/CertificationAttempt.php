<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CertificationAttempt extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'certification_program_id', 'user_id', 'attempt_no', 'status',
        'score', 'started_at', 'submitted_at',
    ];

    protected $casts = [
        'attempt_no' => 'integer',
        'score' => 'decimal:2',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(CertificationProgram::class, 'certification_program_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CertificationAnswer::class);
    }

    public function certification(): HasOne
    {
        return $this->hasOne(UserCertification::class);
    }
}
