<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamResultTransfer extends Model
{
    protected $fillable = [
        'online_exam_attempt_id', 'admission_test_result_id', 'approved_by',
        'source_percentage', 'result',
    ];

    protected $casts = ['source_percentage' => 'decimal:2'];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(OnlineExamAttempt::class, 'online_exam_attempt_id');
    }

    public function admissionTestResult(): BelongsTo
    {
        return $this->belongsTo(AdmissionTestResult::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
