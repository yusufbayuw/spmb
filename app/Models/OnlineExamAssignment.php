<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineExamAssignment extends Model
{
    use HasPublicUuid;

    protected $fillable = ['online_exam_id', 'user_id', 'assigned_by', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            $exam = OnlineExam::query()->find($assignment->online_exam_id);
            $user = User::query()->find($assignment->user_id);
            if (! $exam || ! $exam->unit?->isOperational()
                || ! $user || ! $user->is_active || ! $user->isUser()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_id' => 'Penugasan hanya berlaku untuk pendaftar aktif dan ujian dari unit operasional.',
                ]);
            }

            if ($assignment->exists && $assignment->isDirty(['online_exam_id', 'user_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_id' => 'Penugasan peserta tidak boleh dialihkan ke akun/ujian lain.',
                ]);
            }
        });
    }

    public function exam(): BelongsTo { return $this->belongsTo(OnlineExam::class, 'online_exam_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function attempts(): HasMany { return $this->hasMany(OnlineExamAttempt::class); }
}
