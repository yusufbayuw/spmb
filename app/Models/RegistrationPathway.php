<?php

namespace App\Models;

use Database\Factories\RegistrationPathwayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class RegistrationPathway extends Model
{
    /** @use HasFactory<RegistrationPathwayFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'name',
        'description',
        'is_active',
        'archived_at',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (RegistrationPathway $pathway): void {
            $pathway->created_by ??= auth()->id();
        });

        static::saving(function (RegistrationPathway $pathway): void {
            $user = auth()->user();

            if ($user?->isTU() && $user->unit_id !== (int) $pathway->unit_id) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Jalur pendaftaran hanya dapat dikelola untuk unit Anda.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function scopeAvailableForUnit(Builder $query, int $unitId): Builder
    {
        return $query
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function statusLabel(): string
    {
        if ($this->isArchived()) {
            return 'Diarsipkan';
        }

        return $this->is_active ? 'Aktif' : 'Nonaktif';
    }

    public function setActive(bool $isActive): void
    {
        if ($this->isArchived()) {
            throw ValidationException::withMessages([
                'is_active' => 'Pulihkan jalur dari arsip sebelum mengaktifkannya.',
            ]);
        }

        $this->update(['is_active' => $isActive]);
    }

    public function archive(): void
    {
        $this->update([
            'is_active' => false,
            'archived_at' => now(),
        ]);
    }

    public function restoreFromArchive(): void
    {
        $this->update([
            'is_active' => false,
            'archived_at' => null,
        ]);
    }
}
