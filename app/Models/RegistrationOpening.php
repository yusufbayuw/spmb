<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class RegistrationOpening extends Model
{
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Dijadwalkan',
        'open' => 'Dibuka',
        'closed' => 'Ditutup',
        'archived' => 'Diarsipkan',
    ];

    protected $fillable = [
        'unit_id',
        'study_program_id',
        'academic_year',
        'wave',
        'registration_fee',
        'description',
        'status',
        'opened_at',
        'closed_at',
        'archived_at',
        'created_by',
    ];

    protected $casts = [
        'registration_fee' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (RegistrationOpening $opening): void {
            $opening->created_by ??= auth()->id();
        });

        static::saving(function (RegistrationOpening $opening): void {
            $opening->setAttribute('pathway', $opening->getAttribute('pathway') ?? '');

            $unit = Unit::query()->find($opening->unit_id);
            $program = $opening->study_program_id
                ? StudyProgram::query()->find($opening->study_program_id)
                : null;

            if ($unit?->isHigherEducation() && ! $program) {
                throw ValidationException::withMessages([
                    'study_program_id' => 'Pembukaan pendaftaran perguruan tinggi wajib memilih program studi.',
                ]);
            }

            if ($program && $program->unit_id !== (int) $opening->unit_id) {
                throw ValidationException::withMessages([
                    'study_program_id' => 'Program studi tidak berada pada unit/institusi yang dipilih.',
                ]);
            }

            if ($program && ! $unit?->isHigherEducation()) {
                throw ValidationException::withMessages([
                    'study_program_id' => 'Program studi hanya dapat digunakan untuk unit perguruan tinggi.',
                ]);
            }

            if (($opening->opened_at === null) !== ($opening->closed_at === null)) {
                throw ValidationException::withMessages([
                    'closed_at' => 'Waktu buka dan waktu tutup harus diisi bersamaan.',
                ]);
            }

            if ($opening->opened_at && $opening->closed_at && $opening->closed_at->lessThanOrEqualTo($opening->opened_at)) {
                throw ValidationException::withMessages([
                    'closed_at' => 'Waktu tutup harus setelah waktu buka.',
                ]);
            }

            if (! $opening->exists || $opening->isDirty(['unit_id', 'study_program_id', 'academic_year', 'wave'])) {
                $duplicate = static::query()
                    ->where('unit_id', $opening->unit_id)
                    ->when(
                        $opening->study_program_id,
                        fn (Builder $query): Builder => $query->where('study_program_id', $opening->study_program_id),
                        fn (Builder $query): Builder => $query->whereNull('study_program_id'),
                    )
                    ->where('academic_year', $opening->academic_year)
                    ->where('wave', $opening->wave)
                    ->when($opening->exists, fn (Builder $query): Builder => $query->whereKeyNot($opening->getKey()))
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'wave' => 'Pembukaan dengan unit/program studi, tahun akademik, dan gelombang yang sama sudah ada.',
                    ]);
                }
            }

            if ($opening->status !== 'archived' && $opening->opened_at && $opening->closed_at) {
                $opening->status = match (true) {
                    now()->lt($opening->opened_at) => 'draft',
                    now()->gte($opening->closed_at) => 'closed',
                    default => 'open',
                };
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function scopeCurrentlyOpen(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'archived')
            ->where(function (Builder $availability): void {
                $availability
                    ->where(function (Builder $scheduled): void {
                        $scheduled
                            ->whereNotNull('opened_at')
                            ->whereNotNull('closed_at')
                            ->where('opened_at', '<=', now())
                            ->where('closed_at', '>', now());
                    })
                    ->orWhere(function (Builder $legacy): void {
                        $legacy
                            ->where('status', 'open')
                            ->where(function (Builder $incompleteSchedule): void {
                                $incompleteSchedule->whereNull('opened_at')->orWhereNull('closed_at');
                            });
                    });
            });
    }

    public function scopeVisibleToApplicants(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'archived')
            ->where(function (Builder $visibility): void {
                $visibility
                    ->where(function (Builder $scheduled): void {
                        $scheduled->whereNotNull('opened_at')->whereNotNull('closed_at');
                    })
                    ->orWhere(function (Builder $legacy): void {
                        $legacy
                            ->whereIn('status', ['open', 'closed'])
                            ->where(function (Builder $incompleteSchedule): void {
                                $incompleteSchedule->whereNull('opened_at')->orWhereNull('closed_at');
                            });
                    });
            });
    }

    public function isOpen(): bool
    {
        return $this->operationalStatus() === 'open';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->operationalStatus()] ?? $this->operationalStatus();
    }

    public function operationalStatus(): string
    {
        if ($this->status === 'archived' || $this->archived_at) {
            return 'archived';
        }

        if ($this->opened_at && $this->closed_at) {
            return match (true) {
                now()->lt($this->opened_at) => 'scheduled',
                now()->gte($this->closed_at) => 'closed',
                default => 'open',
            };
        }

        return $this->status;
    }

    public function formattedFee(): string
    {
        return 'Rp '.number_format((float) $this->registration_fee, 0, ',', '.');
    }

    public function label(): string
    {
        return collect([
            $this->unit?->name ?? 'Unit',
            $this->studyProgram?->label(),
            'TA '.$this->academic_year,
            $this->wave,
        ])->filter()->implode(' · ');
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived', 'archived_at' => now()]);
    }
}
