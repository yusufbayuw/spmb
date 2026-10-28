<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineExam extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'unit_id', 'title', 'description', 'duration_minutes', 'max_attempts',
        'passing_percentage', 'shuffle_questions', 'shuffle_options', 'is_published',
        'opens_at', 'closes_at', 'study_program_id', 'blueprint',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'max_attempts' => 'integer',
        'passing_percentage' => 'decimal:2',
        'shuffle_questions' => 'boolean',
        'shuffle_options' => 'boolean',
        'is_published' => 'boolean',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'blueprint' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $exam): void {
            // Apply database defaults before validation on first create.
            $exam->duration_minutes ??= 60;
            $exam->max_attempts ??= 1;
            $exam->passing_percentage ??= 60;
            $exam->shuffle_questions ??= true;
            $exam->shuffle_options ??= true;
            $exam->is_published ??= false;

            if ($exam->exists && $exam->isDirty('unit_id')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'unit_id' => 'Unit paket tes tidak boleh diubah setelah dibuat.',
                ]);
            }

            if ($exam->duration_minutes < 1 || $exam->duration_minutes > 360
                || $exam->max_attempts < 1 || $exam->max_attempts > 20
                || (float) $exam->passing_percentage < 0 || (float) $exam->passing_percentage > 100
                || ($exam->opens_at && $exam->closes_at && $exam->opens_at->gte($exam->closes_at))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'exam' => 'Durasi, percobaan, ambang batas, atau rentang waktu ujian tidak valid.',
                ]);
            }

            if ($exam->study_program_id) {
                $program = StudyProgram::query()->find($exam->study_program_id);
                if (! $program || ! $program->is_active || (int) $program->unit_id !== (int) $exam->unit_id) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'study_program_id' => 'Program studi harus aktif dan berasal dari unit paket ujian.',
                    ]);
                }
            }

            if ($exam->exists && $exam->attempts()->exists()
                && $exam->isDirty('study_program_id')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'study_program_id' => 'Prodi ujian tidak dapat diubah setelah peserta mulai mengerjakan.',
                ]);
            }

            if ($exam->exists && $exam->getOriginal('is_published')
                && $exam->is_published && $exam->isDirty('blueprint')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'blueprint' => 'Batalkan publikasi paket sebelum mengubah blueprint.',
                ]);
            }

            if ($exam->is_published && app(\App\Services\OnlineExamBlueprint::class)->isEnabled($exam)) {
                app(\App\Services\OnlineExamBlueprint::class)->validate($exam);

                return;
            }

            if ($exam->is_published) {
                $itemsCount = $exam->exists ? $exam->items()->count() : 0;
                $validCount = $exam->exists
                    ? $exam->items()->whereHas('question', fn ($query) => $query
                        ->where('unit_id', $exam->unit_id)->where('is_active', true))->count()
                    : 0;

                if ($itemsCount === 0 || $itemsCount !== $validCount) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'is_published' => 'Semua soal paket harus aktif dan berasal dari unit yang sama sebelum ujian diterbitkan.',
                    ]);
                }
            }
        });
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }

    public function items(): HasMany { return $this->hasMany(OnlineExamItem::class)->orderBy('sort_order')->orderBy('id'); }

    public function studyProgram(): BelongsTo { return $this->belongsTo(StudyProgram::class); }

    public function assignments(): HasMany { return $this->hasMany(OnlineExamAssignment::class); }

    public function attempts(): HasMany { return $this->hasMany(OnlineExamAttempt::class); }

    public function isOpen(): bool
    {
        return $this->is_published
            && $this->unit?->isOperational()
            && (! $this->opens_at || $this->opens_at->lte(now()))
            && (! $this->closes_at || $this->closes_at->gt(now()));
    }
}
