<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineExamQuestion extends Model
{
    use HasPublicUuid;

    public const TYPES = [
        'single_choice' => 'Pilihan Ganda (Satu Jawaban)',
        'multiple_choice' => 'Pilihan Ganda (Banyak Jawaban)',
        'true_false' => 'Benar / Salah',
        'matching' => 'Menjodohkan',
        'short_answer' => 'Jawaban Singkat',
        'numerical' => 'Numerik',
        'essay' => 'Esai',
    ];

    protected $fillable = ['unit_id', 'type', 'prompt', 'options', 'grading_config', 'points', 'is_active'];

    protected $casts = [
        'options' => 'array',
        'grading_config' => 'array',
        'points' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Correct answers must only be accessed by server-side grading and authorized editors.
    protected $hidden = ['grading_config'];

    protected static function booted(): void
    {
        static::saving(function (self $question): void {
            if ($question->exists && $question->isDirty('unit_id')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'unit_id' => 'Unit soal tidak dapat dipindahkan. Buat soal baru.',
                ]);
            }

            app(\App\Services\OnlineExamQuestionValidator::class)->validate($question);
        });
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }

    public function items(): HasMany { return $this->hasMany(OnlineExamItem::class); }
}
