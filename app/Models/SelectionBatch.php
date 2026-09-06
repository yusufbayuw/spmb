<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SelectionBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class SelectionBatch extends Model
{
    /** @use HasFactory<SelectionBatchFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected $fillable = [
        'registration_opening_id', 'registration_pathway_id', 'name', 'waitlist_limit', 'status',
        'ranked_by', 'ranked_at', 'finalized_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return ['ranked_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $batch): void {
            $opening = RegistrationOpening::query()->findOrFail($batch->registration_opening_id);
            $pathway = $batch->registration_pathway_id
                ? RegistrationPathway::query()->findOrFail($batch->registration_pathway_id)
                : null;
            if ($pathway && $pathway->unit_id !== $opening->unit_id) {
                throw ValidationException::withMessages(['registration_pathway_id' => 'Jalur pendaftaran harus berada pada unit pembukaan yang sama.']);
            }
            if (auth()->user()?->isTU() && auth()->user()->unit_id !== $opening->unit_id) {
                throw ValidationException::withMessages(['registration_opening_id' => 'Batch seleksi hanya dapat dikelola untuk unit Anda.']);
            }
            if ($batch->exists && $batch->getOriginal('status') === 'finalized' && $batch->isDirty()) {
                throw ValidationException::withMessages(['selection_batch' => 'Batch yang telah difinalkan tidak dapat diubah.']);
            }
        });
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(RegistrationOpening::class, 'registration_opening_id');
    }

    public function pathway(): BelongsTo
    {
        return $this->belongsTo(RegistrationPathway::class, 'registration_pathway_id');
    }

    public function selections(): HasMany
    {
        return $this->hasMany(Selection::class);
    }
}
