<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StudyProgram extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'unit_id',
        'education_level_id',
        'code',
        'name',
        'degree_level',
        'faculty',
        'description',
        'max_age',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'max_age' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (StudyProgram $program): void {
            $unit = Unit::query()->find($program->unit_id);

            if (! $unit?->isHigherEducation()) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Program studi hanya dapat dibuat pada unit perguruan tinggi.',
                ]);
            }

            if (Schema::hasTable('education_levels') && Schema::hasColumn('study_programs', 'education_level_id')) {
                $level = $program->education_level_id
                    ? EducationLevel::query()->find($program->education_level_id)
                    : EducationLevel::query()
                        ->where('code', mb_strtoupper(trim((string) $program->degree_level)))
                        ->first();

                if (! $level || ! $level->isHigherEducation()) {
                    throw ValidationException::withMessages([
                        'education_level_id' => 'Pilih jenjang perguruan tinggi yang valid.',
                    ]);
                }

                $program->education_level_id = $level->id;
                $program->degree_level = $level->code;
            }

            $code = mb_strtoupper(trim((string) $program->code));

            if ($code === '') {
                throw ValidationException::withMessages([
                    'code' => 'Kode program studi wajib diisi.',
                ]);
            }

            if (! preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $code)) {
                throw ValidationException::withMessages([
                    'code' => 'Kode program studi hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).',
                ]);
            }

            $duplicate = static::query()
                ->where('unit_id', $program->unit_id)
                ->whereRaw('UPPER(code) = ?', [$code])
                ->when($program->exists, fn ($query) => $query->whereKeyNot($program->getKey()))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'code' => 'Kode program studi sudah digunakan pada unit ini.',
                ]);
            }

            $program->code = $code;
        });
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function registrationOpenings()
    {
        return $this->hasMany(RegistrationOpening::class);
    }

    public function virtualAccounts()
    {
        return $this->hasMany(VirtualAccount::class);
    }

    public function label(): string
    {
        $level = $this->educationLevel?->code ?? $this->degree_level;

        return trim($level.' '.$this->name);
    }

    public function assertApplicantAge(string|\DateTimeInterface|null $birthDate): void
    {
        if (! $this->max_age || ! $birthDate) {
            return;
        }

        $age = Carbon::parse($birthDate)->age;

        if ($age <= $this->max_age) {
            return;
        }

        throw ValidationException::withMessages([
            'birth_date' => "Usia pendaftar untuk {$this->label()} maksimal {$this->max_age} tahun pada saat mendaftar.",
        ]);
    }
}
