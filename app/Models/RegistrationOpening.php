<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RegistrationOpening extends Model
{
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'open' => 'Dibuka',
        'closed' => 'Ditutup',
        'archived' => 'Diarsipkan',
    ];

    protected $fillable = [
        'unit_id',
        'study_program_id',
        'academic_year',
        'wave',
        'pathway',
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

            $duplicate = static::query()
                ->where('unit_id', $opening->unit_id)
                ->when(
                    $opening->study_program_id,
                    fn (Builder $query): Builder => $query->where('study_program_id', $opening->study_program_id),
                    fn (Builder $query): Builder => $query->whereNull('study_program_id'),
                )
                ->where('academic_year', $opening->academic_year)
                ->where('wave', $opening->wave)
                ->where('pathway', $opening->pathway)
                ->when($opening->exists, fn (Builder $query): Builder => $query->whereKeyNot($opening->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'wave' => 'Pembukaan dengan unit/program studi, tahun akademik, gelombang, dan jalur yang sama sudah ada.',
                ]);
            }
        });
    }

    public function unit() { return $this->belongsTo(Unit::class); }
    public function studyProgram() { return $this->belongsTo(StudyProgram::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function registrations() { return $this->hasMany(Registration::class); }

    public function scopeVisibleToApplicants(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'closed']);
    }

    public function isOpen(): bool { return $this->status === 'open'; }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

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
            'Jalur '.$this->pathway,
        ])->filter()->implode(' · ');
    }

    public function open(): void
    {
        $this->update([
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
            'archived_at' => null,
        ]);
    }

    public function close(): void
    {
        $this->update(['status' => 'closed', 'closed_at' => now()]);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived', 'archived_at' => now()]);
    }
}
