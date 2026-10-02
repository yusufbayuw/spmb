<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingModuleQuestion extends Model
{
    use HasPublicUuid;

    public const TYPES = [
        'single_choice' => 'Pilihan Tunggal',
        'true_false' => 'Benar / Salah',
    ];

    protected $fillable = [
        'training_module_assessment_id', 'seed_key', 'type', 'question', 'options',
        'correct_answer', 'explanation', 'weight', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'weight' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(TrainingModuleAssessment::class, 'training_module_assessment_id');
    }
}
