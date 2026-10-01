<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingProgress extends Model
{
    use HasPublicUuid;

    protected $table = 'training_progress';

    protected $fillable = [
        'training_enrollment_id', 'training_lesson_id', 'status', 'progress_percent',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrollment::class, 'training_enrollment_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(TrainingLesson::class, 'training_lesson_id');
    }
}
