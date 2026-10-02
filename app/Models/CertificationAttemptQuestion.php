<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CertificationAttemptQuestion extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'certification_attempt_id', 'certification_question_id', 'type', 'question',
        'options', 'correct_answer', 'explanation', 'weight', 'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'weight' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CertificationAttempt::class, 'certification_attempt_id');
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(CertificationQuestion::class, 'certification_question_id');
    }

    public function answer(): HasOne
    {
        return $this->hasOne(CertificationAnswer::class, 'certification_attempt_question_id');
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
