<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingLesson extends Model
{
    use HasPublicUuid;

    public const TYPES = [
        'content' => 'Materi',
        'guide' => 'Panduan',
        'video' => 'Video',
        'quiz' => 'Latihan',
        'simulation' => 'Simulasi',
        'practice' => 'Praktik',
    ];

    protected $fillable = [
        'training_module_id', 'title', 'type', 'content', 'video_url',
        'duration_minutes', 'sort_order', 'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'duration_minutes' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(TrainingProgress::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
