<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticalRun extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'practical_scenario_id', 'certification_attempt_id', 'user_id', 'attempt_no',
        'status', 'score', 'passed', 'started_at', 'submitted_at',
    ];

    protected $casts = [
        'attempt_no' => 'integer',
        'score' => 'decimal:2',
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(PracticalScenario::class, 'practical_scenario_id');
    }

    public function theoryAttempt(): BelongsTo
    {
        return $this->belongsTo(CertificationAttempt::class, 'certification_attempt_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sandboxRecords(): HasMany
    {
        return $this->hasMany(PracticalSandboxRecord::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PracticalRunEvent::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(PracticalRunResult::class);
    }

    public function isExpired(): bool
    {
        $limit = $this->scenario?->time_limit_minutes;

        return $limit
            ? $this->started_at->copy()->addMinutes($limit)->isPast()
            : false;
    }
}
