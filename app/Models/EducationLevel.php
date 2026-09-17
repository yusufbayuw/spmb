<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationLevel extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'early_childhood' => 'Pendidikan Anak Usia Dini',
        'school' => 'Sekolah',
        'higher_education' => 'Perguruan Tinggi',
    ];

    public const DEFAULT_LEVELS = [
        ['code' => 'DC', 'name' => 'Daycare', 'category' => 'early_childhood', 'sort_order' => 10],
        ['code' => 'KB', 'name' => 'Kelompok Bermain', 'category' => 'early_childhood', 'sort_order' => 20],
        ['code' => 'TK', 'name' => 'Taman Kanak-Kanak', 'category' => 'early_childhood', 'sort_order' => 30],
        ['code' => 'SD', 'name' => 'Sekolah Dasar', 'category' => 'school', 'sort_order' => 40],
        ['code' => 'SMP', 'name' => 'Sekolah Menengah Pertama', 'category' => 'school', 'sort_order' => 50],
        ['code' => 'SMA', 'name' => 'Sekolah Menengah Atas', 'category' => 'school', 'sort_order' => 60],
        ['code' => 'D3', 'name' => 'Diploma III', 'category' => 'higher_education', 'sort_order' => 70],
        ['code' => 'D4', 'name' => 'Sarjana Terapan', 'category' => 'higher_education', 'sort_order' => 80],
        ['code' => 'S1', 'name' => 'Sarjana', 'category' => 'higher_education', 'sort_order' => 90],
        ['code' => 'S2', 'name' => 'Magister', 'category' => 'higher_education', 'sort_order' => 100],
        ['code' => 'S3', 'name' => 'Doktor', 'category' => 'higher_education', 'sort_order' => 110],
    ];

    protected $fillable = [
        'code',
        'name',
        'category',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function studyPrograms(): HasMany
    {
        return $this->hasMany(StudyProgram::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    public function isHigherEducation(): bool
    {
        return $this->category === 'higher_education';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
