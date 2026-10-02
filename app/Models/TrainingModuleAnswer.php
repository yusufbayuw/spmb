<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingModuleAnswer extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'training_module_attempt_id', 'training_module_attempt_question_id',
        'training_module_question_id', 'answer', 'is_correct', 'score',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'score' => 'decimal:2',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TrainingModuleAttempt::class, 'training_module_attempt_id');
    }

    public function attemptQuestion(): BelongsTo
    {
        return $this->belongsTo(TrainingModuleAttemptQuestion::class, 'training_module_attempt_question_id');
    }
}
