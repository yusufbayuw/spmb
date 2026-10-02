<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingModuleAttempt extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'training_module_assessment_id', 'training_enrollment_id', 'user_id',
        'attempt_no', 'status', 'score', 'passing_score_snapshot',
        'question_count_snapshot', 'started_at', 'submitted_at',
    ];

    protected $casts = [
        'attempt_no' => 'integer',
        'score' => 'decimal:2',
        'passing_score_snapshot' => 'integer',
        'question_count_snapshot' => 'integer',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(TrainingModuleAssessment::class, 'training_module_assessment_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrollment::class, 'training_enrollment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(TrainingModuleAttemptQuestion::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TrainingModuleAnswer::class);
    }
}
