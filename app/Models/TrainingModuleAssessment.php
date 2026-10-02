<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingModuleAssessment extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'training_module_id', 'seed_key', 'title', 'description', 'passing_score',
        'question_count', 'max_attempts', 'is_required', 'is_active',
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'question_count' => 'integer',
        'max_attempts' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TrainingModuleQuestion::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingModuleAttempt::class);
    }
}
