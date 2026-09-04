<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    public const INSTITUTION_TYPES = [
        'early_childhood' => 'Pendidikan Anak Usia Dini',
        'school' => 'Sekolah',
        'university' => 'Perguruan Tinggi',
    ];

    protected $fillable = ['name', 'code', 'institution_type', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function registrationOpenings()
    {
        return $this->hasMany(RegistrationOpening::class);
    }

    public function registrationPathways(): HasMany
    {
        return $this->hasMany(RegistrationPathway::class);
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
        return $this->hasMany(StudyProgram::class)->orderBy('sort_order')->orderBy('degree_level')->orderBy('name');
    }

    public function isHigherEducation(): bool
    {
        return $this->institution_type === 'university';
    }

    public function institutionTypeLabel(): string
    {
        return self::INSTITUTION_TYPES[$this->institution_type] ?? $this->institution_type;
    }
}
