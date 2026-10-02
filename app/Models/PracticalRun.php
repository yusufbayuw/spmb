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
        'practical_scenario_id', 'certification_attempt_id', 'user_id',
        'scenario_code_snapshot', 'scenario_name_snapshot', 'instructions_snapshot',
        'time_limit_minutes_snapshot', 'passing_score_snapshot',
        'attempt_no', 'status', 'score', 'passed', 'started_at', 'submitted_at',
    ];

    protected $casts = [
        'time_limit_minutes_snapshot' => 'integer',
        'passing_score_snapshot' => 'integer',
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

    public function runActions(): HasMany
    {
        return $this->hasMany(PracticalRunAction::class)->orderBy('sort_order')->orderBy('id');
    }

    public function runAssertions(): HasMany
    {
        return $this->hasMany(PracticalRunAssertion::class)->orderBy('sort_order')->orderBy('id');
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
        $limit = $this->time_limit_minutes_snapshot ?? $this->scenario?->time_limit_minutes;

        return $limit
            ? $this->started_at->copy()->addMinutes($limit)->isPast()
            : false;
    }

    public function displayName(): string
    {
        return $this->scenario_name_snapshot ?: $this->scenario?->name ?: '-';
    }

    public function displayInstructions(): string
    {
        return $this->instructions_snapshot ?: $this->scenario?->instructions ?: '';
    }
}
