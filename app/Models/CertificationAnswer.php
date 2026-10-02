<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificationAnswer extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'certification_attempt_id', 'certification_question_id',
        'certification_attempt_question_id', 'answer', 'is_correct', 'score',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'score' => 'decimal:2',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CertificationAttempt::class, 'certification_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(CertificationQuestion::class, 'certification_question_id');
    }

    public function attemptQuestion(): BelongsTo
    {
        return $this->belongsTo(CertificationAttemptQuestion::class, 'certification_attempt_question_id');
    }
}
