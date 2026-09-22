<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Faq extends Model
{
    protected $fillable = [
        'unit_id',
        'study_program_id',
        'registration_pathway_id',
        'question',
        'answer',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Faq $faq): void {
            $user = auth()->user();

            if ($user?->isAdminUnit() && (int) $user->unit_id !== (int) $faq->unit_id) {
                throw ValidationException::withMessages([
                    'unit_id' => 'FAQ hanya dapat dikelola untuk unit Anda.',
                ]);
            }

            if ($faq->study_program_id && ! StudyProgram::query()
                ->whereKey($faq->study_program_id)
                ->where('unit_id', $faq->unit_id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'study_program_id' => 'Program studi tidak sesuai dengan unit FAQ.',
                ]);
            }

            if ($faq->registration_pathway_id && ! RegistrationPathway::query()
                ->whereKey($faq->registration_pathway_id)
                ->where('unit_id', $faq->unit_id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'registration_pathway_id' => 'Jalur pendaftaran tidak sesuai dengan unit FAQ.',
                ]);
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function registrationPathway(): BelongsTo
    {
        return $this->belongsTo(RegistrationPathway::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function contextLabel(): ?string
    {
        return collect([
            $this->studyProgram?->label(),
            $this->registrationPathway?->name ? 'Jalur '.$this->registrationPathway->name : null,
        ])->filter()->implode(' · ') ?: null;
    }
}
