<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificationProgram extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'code', 'name', 'description', 'target_role', 'version', 'passing_score',
        'valid_months', 'training_program_id', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'valid_months' => 'integer',
        'is_active' => 'boolean',
    ];

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CertificationQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(CertificationAttempt::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(UserCertification::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereIn('target_role', $user->getRoleNames()->all());
    }

    public function roleLabel(): string
    {
        return TrainingProgram::ROLE_LABELS[$this->target_role] ?? $this->target_role;
    }
}
