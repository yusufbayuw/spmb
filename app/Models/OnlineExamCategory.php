<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class OnlineExamCategory extends Model
{
    protected $fillable = ['unit_id', 'code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $category->code = mb_strtoupper(trim((string) $category->code));
            $category->name = trim((string) $category->name);

            if ($category->exists && $category->isDirty('unit_id')) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Kategori soal tidak dapat dipindahkan ke unit lain.',
                ]);
            }

            if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{0,39}$/', $category->code)
                || $category->name === '' || mb_strlen($category->name) > 150) {
                throw ValidationException::withMessages([
                    'code' => 'Kode kategori harus unik dan hanya menggunakan huruf, angka, - atau _.',
                ]);
            }

            if (static::query()->where('unit_id', $category->unit_id)
                ->where('code', $category->code)
                ->when($category->exists, fn ($q) => $q->whereKeyNot($category->id))
                ->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode kategori sudah digunakan pada unit ini.']);
            }

            if ($category->exists && $category->isDirty('is_active') && ! $category->is_active
                && \App\Models\OnlineExam::query()->where('unit_id', $category->unit_id)
                    ->where('is_published', true)->whereNotNull('blueprint')->get()
                    ->contains(fn ($exam) => collect($exam->blueprint ?? [])
                        ->contains(fn ($row) => (int) ($row['category_id'] ?? 0) === (int) $category->id))) {
                throw ValidationException::withMessages([
                    'is_active' => 'Kategori dipakai blueprint paket yang terbit. Batalkan publikasi dahulu.',
                ]);
            }
        });

        static::deleting(function (self $category): void {
            if (OnlineExam::query()->where('unit_id', $category->unit_id)
                ->whereNotNull('blueprint')->get()
                ->contains(fn ($exam) => collect($exam->blueprint ?? [])
                    ->contains(fn ($row) => (int) ($row['category_id'] ?? 0) === (int) $category->id))) {
                throw ValidationException::withMessages([
                    'category' => 'Kategori sedang digunakan blueprint paket ujian.',
                ]);
            }

            if ($category->questions()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Kategori masih memiliki soal. Nonaktifkan kategori sebagai gantinya.',
                ]);
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(OnlineExamQuestion::class, 'online_exam_category_id');
    }
}
