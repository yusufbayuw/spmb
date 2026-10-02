<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificationQuestion extends Model
{
    use HasPublicUuid;

    public const TYPES = [
        'single_choice' => 'Pilihan Tunggal',
        'true_false' => 'Benar / Salah',
    ];

    protected $fillable = [
        'certification_program_id', 'seed_key', 'type', 'question', 'options', 'correct_answer',
        'explanation', 'weight', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(CertificationProgram::class, 'certification_program_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CertificationAnswer::class);
    }

    public function resolvedOptions(): array
    {
        if ($this->type === 'true_false') {
            return ['1' => 'Benar', '0' => 'Salah'];
        }

        return collect($this->options ?? [])
            ->mapWithKeys(fn (mixed $label, mixed $key): array => [(string) $key => (string) $label])
            ->all();
    }
}
