<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamItem extends Model
{
    protected $fillable = ['online_exam_id', 'online_exam_question_id', 'sort_order'];

    public function exam(): BelongsTo { return $this->belongsTo(OnlineExam::class, 'online_exam_id'); }

    public function question(): BelongsTo { return $this->belongsTo(OnlineExamQuestion::class, 'online_exam_question_id'); }
}
