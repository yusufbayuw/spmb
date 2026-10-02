<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingModuleAttemptQuestion extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'training_module_attempt_id', 'training_module_question_id', 'type',
        'question', 'options', 'correct_answer', 'explanation', 'weight', 'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'weight' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TrainingModuleAttempt::class, 'training_module_attempt_id');
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(TrainingModuleQuestion::class, 'training_module_question_id');
    }

    public function resolvedOptions(): array
    {
        if ($this->type === 'true_false') {
            return ['1' => 'Benar', '0' => 'Salah'];
        }

        return collect($this->options ?? [])
            ->mapWithKeys(fn (mixed $label, mixed $key): array => [(string) $key => (string) $label])
            ->all();
    }
}
