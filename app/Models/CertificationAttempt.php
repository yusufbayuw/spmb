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
        'certification_program_id', 'user_id',
        'program_code_snapshot', 'program_name_snapshot', 'program_version_snapshot',
        'passing_score_snapshot', 'theory_weight_snapshot', 'practical_weight_snapshot',
        'practical_passing_score_snapshot', 'valid_months_snapshot',
        'question_count_snapshot', 'time_limit_minutes_snapshot',
        'required_practical_scenario_ids_snapshot',
        'attempt_no', 'status', 'score', 'started_at', 'expires_at', 'submitted_at',
    ];

    protected $casts = [
        'passing_score_snapshot' => 'integer',
        'theory_weight_snapshot' => 'integer',
        'practical_weight_snapshot' => 'integer',
        'practical_passing_score_snapshot' => 'integer',
        'valid_months_snapshot' => 'integer',
        'question_count_snapshot' => 'integer',
        'time_limit_minutes_snapshot' => 'integer',
        'required_practical_scenario_ids_snapshot' => 'array',
        'attempt_no' => 'integer',
        'score' => 'decimal:2',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(CertificationAttemptQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CertificationAnswer::class);
    }

    public function practicalRuns(): HasMany
    {
        return $this->hasMany(PracticalRun::class);
    }

    public function certification(): HasOne
    {
        return $this->hasOne(UserCertification::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function hasPracticalRequirementsSnapshot(): bool
    {
        return $this->getAttribute('required_practical_scenario_ids_snapshot') !== null;
    }

    public function requiredPracticalScenarioIds(): array
    {
        return collect($this->required_practical_scenario_ids_snapshot ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values()
            ->all();
    }
}
