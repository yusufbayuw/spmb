<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StudyProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
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

    public function unit() { return $this->belongsTo(Unit::class); }
    public function registrationOpenings() { return $this->hasMany(RegistrationOpening::class); }

    public function label(): string
    {
        return trim($this->degree_level.' '.$this->name);
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
