<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContinuationRegistrationLink extends Model
{
    protected $fillable = [
        'continuation_candidate_id',
        'registration_id',
        'matched_by',
        'source_snapshot',
        'prefilled_fields',
        'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'source_snapshot' => 'array',
            'prefilled_fields' => 'array',
            'matched_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ContinuationCandidate::class, 'continuation_candidate_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
