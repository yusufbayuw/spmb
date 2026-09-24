<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Support\SpmbOperationalMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class Unit extends Model
{
    use HasFactory;
    use HasPublicUuid;

    public const INSTITUTION_TYPES = [
        'early_childhood' => 'Pendidikan Anak Usia Dini',
        'school' => 'Sekolah',
        'university' => 'Perguruan Tinggi',
    ];

    protected $fillable = [
        'name',
        'code',
        'institution_type',
        'education_level_id',
        'description',
        'public_headline',
        'public_body',
        'public_contact_name',
        'public_email',
        'public_phone',
        'public_whatsapp',
        'public_service_hours',
        'public_website_url',
        'public_address',
        'logo_path',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (Unit $unit): void {
            $unit->institution_type ??= 'school';

            if (! SpmbOperationalMode::allowsInstitutionType($unit->institution_type)) {
                throw ValidationException::withMessages([
                    'institution_type' => 'Jenis institusi tidak tersedia pada mode operasional aplikasi saat ini.',
                ]);
            }

            if (! Schema::hasTable('education_levels') || ! Schema::hasColumn('units', 'education_level_id')) {
                return;
            }

            if ($unit->institution_type === 'university') {
                $unit->education_level_id = null;

                return;
            }

            if (! $unit->education_level_id && filled($unit->code)) {
                $matchingLevel = EducationLevel::query()
                    ->where('code', mb_strtoupper(trim((string) $unit->code)))
                    ->where('category', $unit->institution_type)
                    ->first();

                if ($matchingLevel) {
                    $unit->education_level_id = $matchingLevel->id;
                }
            }

            if (! $unit->education_level_id) {
                return;
            }

            $level = EducationLevel::query()->find($unit->education_level_id);

            if (! $level || $level->category !== $unit->institution_type) {
                throw ValidationException::withMessages([
                    'education_level_id' => 'Jenjang pendidikan tidak sesuai dengan jenis institusi yang dipilih.',
                ]);
            }
        });
    }

    public function scopeForOperationalMode(Builder $query): Builder
    {
        return $query->whereIn('institution_type', SpmbOperationalMode::allowedInstitutionTypes());
    }

    public function isAllowedByOperationalMode(): bool
    {
        return SpmbOperationalMode::allowsInstitutionType($this->institution_type);
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function registrationOpenings()
    {
        return $this->hasMany(RegistrationOpening::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function registrationPathways(): HasMany
    {
        return $this->hasMany(RegistrationPathway::class);
    }

    public function admissionQuotas(): HasMany
    {
        return $this->hasManyThrough(AdmissionQuota::class, RegistrationOpening::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function admissionTests()
    {
        return $this->hasMany(AdmissionTest::class)->orderBy('sort_order');
    }

    public function virtualAccounts()
    {
        return $this->hasMany(VirtualAccount::class);
    }

    public function studyPrograms()
    {
        return $this->hasMany(StudyProgram::class)
            ->orderBy(
                EducationLevel::query()
                    ->select('sort_order')
                    ->whereColumn('education_levels.id', 'study_programs.education_level_id')
            )
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function isHigherEducation(): bool
    {
        return $this->institution_type === 'university';
    }

    public function institutionTypeLabel(): string
    {
        return self::INSTITUTION_TYPES[$this->institution_type] ?? $this->institution_type;
    }

    public function hasPublicContact(): bool
    {
        return filled($this->public_contact_name)
            || filled($this->public_email)
            || filled($this->public_phone)
            || filled($this->public_whatsapp)
            || filled($this->public_service_hours)
            || filled($this->public_website_url)
            || filled($this->public_address);
    }
}
