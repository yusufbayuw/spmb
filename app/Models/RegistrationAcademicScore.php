<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationAcademicScore extends Model
{
    protected $fillable = [
        'registration_id',
        'grade_key',
        'subject_key',
        'assessment_key',
        'score',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
