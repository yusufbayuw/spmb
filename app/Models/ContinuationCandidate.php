<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContinuationCandidate extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'unit_id',
        'academic_year',
        'source_school_name',
        'source_file',
        'import_batch_uuid',
        'source_row',
        'source_key',
        'nik',
        'birth_date',
        'full_name',
        'nisn',
        'nipd',
        'prefill_data',
        'raw_data',
        'is_active',
        'imported_by',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'prefill_data' => 'array',
            'raw_data' => 'array',
            'is_active' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    public function scopeMatchable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('nik')
            ->whereNotNull('birth_date');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ContinuationRegistrationLink::class);
    }
}
