<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineExamItem extends Model
{
    protected $fillable = ['online_exam_id', 'online_exam_question_id', 'sort_order'];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $exam = OnlineExam::query()->find($item->online_exam_id);
            $question = OnlineExamQuestion::query()->find($item->online_exam_question_id);

            if (! $exam || ! $question || ! $exam->unit?->isOperational()
                || ! $question->is_active
                || (int) $exam->unit_id !== (int) $question->unit_id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'online_exam_question_id' => 'Soal harus berasal dari unit yang sama dengan paket tes.',
                ]);
            }

            if ($exam->is_published) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'online_exam_id' => 'Batalkan publikasi paket sebelum mengubah daftar soal.',
                ]);
            }

            if ($item->exists && $item->isDirty(['online_exam_id', 'online_exam_question_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'online_exam_question_id' => 'Relasi soal paket tidak boleh diubah. Tambahkan item baru.',
                ]);
            }
        });

        static::deleting(function (self $item): void {
            // Do not trust a cached relation: the package may have been
            // unpublished after a previous failed delete in this session.
            $exam = OnlineExam::query()->find($item->online_exam_id);
            if ($exam && ($exam->is_published || $exam->attempts()->exists())) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'online_exam_id' => 'Daftar soal paket yang sudah terbit atau sudah dikerjakan tidak boleh dihapus.',
                ]);
            }
        });
    }

    public function exam(): BelongsTo { return $this->belongsTo(OnlineExam::class, 'online_exam_id'); }

    public function question(): BelongsTo { return $this->belongsTo(OnlineExamQuestion::class, 'online_exam_question_id'); }
}
