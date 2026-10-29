<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineExamAttempt extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'online_exam_assignment_id', 'online_exam_id', 'user_id', 'attempt_no',
        'status', 'duration_minutes_snapshot', 'passing_percentage_snapshot',
        'earned_points', 'maximum_points', 'percentage', 'passed',
        'started_at', 'expires_at', 'submitted_at', 'reviewed_at',
    ];

    protected $casts = [
        'duration_minutes_snapshot' => 'integer',
        'attempt_no' => 'integer',
        'passing_percentage_snapshot' => 'decimal:2',
        'earned_points' => 'decimal:2',
        'maximum_points' => 'decimal:2',
        'percentage' => 'decimal:2',
        'passed' => 'boolean',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function assignment(): BelongsTo { return $this->belongsTo(OnlineExamAssignment::class, 'online_exam_assignment_id'); }

    public function exam(): BelongsTo { return $this->belongsTo(OnlineExam::class, 'online_exam_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function items(): HasMany { return $this->hasMany(OnlineExamAttemptItem::class)->orderBy('sort_order'); }

    public function transfer(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OnlineExamResultTransfer::class, 'online_exam_attempt_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['submitted', 'pending_review', 'graded', 'expired'], true);
    }
}
