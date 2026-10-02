<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingModule extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'training_program_id', 'seed_key', 'title', 'description', 'sort_order', 'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(TrainingLesson::class)->orderBy('sort_order')->orderBy('id');
    }
}
