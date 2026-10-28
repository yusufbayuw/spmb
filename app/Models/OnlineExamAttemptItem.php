<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamAttemptItem extends Model
{
    protected $fillable = [
        'online_exam_attempt_id', 'online_exam_question_id', 'type',
        'display_snapshot', 'grading_snapshot', 'answer', 'points', 'earned_points',
        'sort_order', 'answered_at', 'graded_at', 'reviewed_by',
    ];

    protected $casts = [
        'display_snapshot' => 'array',
        'grading_snapshot' => 'array',
        'answer' => 'array',
        'points' => 'decimal:2',
        'earned_points' => 'decimal:2',
        'sort_order' => 'integer',
        'answered_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    protected $hidden = ['grading_snapshot'];

    public function attempt(): BelongsTo { return $this->belongsTo(OnlineExamAttempt::class, 'online_exam_attempt_id'); }

    public function sourceQuestion(): BelongsTo { return $this->belongsTo(OnlineExamQuestion::class, 'online_exam_question_id'); }
}
