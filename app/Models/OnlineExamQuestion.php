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

    protected $fillable = ['unit_id', 'type', 'prompt', 'options', 'grading_config', 'points', 'is_active', 'online_exam_category_id', 'difficulty', 'competency_tag'];

    protected $casts = [
        'options' => 'array',
        'grading_config' => 'array',
        'points' => 'decimal:2',
        'is_active' => 'boolean',
        'online_exam_category_id' => 'integer',
    ];

    // Correct answers must only be accessed by server-side grading and authorized editors.
    protected $hidden = ['grading_config'];

    protected static function booted(): void
    {
        static::saving(function (self $question): void {
            // An active question referenced by a published package cannot
            // be deactivated while that package is available to participants.
            if ($question->exists && $question->isDirty('is_active') && ! $question->is_active
                && $question->items()->whereHas('exam', fn ($query) => $query
                    ->where('is_published', true))->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'is_active' => 'Batalkan publikasi paket yang menggunakan soal ini sebelum menonaktifkannya.',
                ]);
            }

            if ($question->exists && $question->isDirty('unit_id')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'unit_id' => 'Unit soal tidak dapat dipindahkan. Buat soal baru.',
                ]);
            }

            $question->difficulty ??= 'medium';

            if (! in_array($question->difficulty, ['easy', 'medium', 'hard'], true)
                || mb_strlen((string) $question->competency_tag) > 150) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'difficulty' => 'Kesulitan soal harus mudah, sedang, atau sulit.',
                ]);
            }

            if ($question->online_exam_category_id) {
                $category = OnlineExamCategory::query()->find($question->online_exam_category_id);
                if (! $category || (int) $category->unit_id !== (int) $question->unit_id
                    || ! $category->is_active) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'online_exam_category_id' => 'Kategori harus aktif dan berasal dari unit yang sama.',
                    ]);
                }
            }

            // Do not mutate the membership of a published random-question pool.
            if ($question->exists && $question->isDirty([
                'online_exam_category_id', 'difficulty', 'is_active',
            ]) && $question->getRawOriginal('online_exam_category_id')
                && OnlineExam::query()->where('unit_id', $question->unit_id)
                    ->where('is_published', true)->whereNotNull('blueprint')->get()
                    ->contains(fn ($exam) => collect($exam->blueprint ?? [])
                        ->contains(fn ($row) =>
                            (int) ($row['category_id'] ?? 0) === (int) $question->getRawOriginal('online_exam_category_id')))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'online_exam_category_id' => 'Soal berada dalam blueprint paket terbit. Batalkan publikasi dahulu.',
                ]);
            }

            app(\App\Services\OnlineExamQuestionValidator::class)->validate($question);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(OnlineExamCategory::class, 'online_exam_category_id');
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }

    public function items(): HasMany { return $this->hasMany(OnlineExamItem::class); }
}
