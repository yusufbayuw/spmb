<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineExamAssignment extends Model
{
    use HasPublicUuid;

    protected $fillable = ['online_exam_id', 'user_id', 'assigned_by', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function exam(): BelongsTo { return $this->belongsTo(OnlineExam::class, 'online_exam_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function attempts(): HasMany { return $this->hasMany(OnlineExamAttempt::class); }
}
