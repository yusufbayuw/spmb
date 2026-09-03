<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AdmissionTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id', 'study_program_id', 'name', 'code', 'description', 'sort_order',
        'is_required', 'is_active', 'scheduled_at', 'location', 'passing_score', 'result_type',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'scheduled_at' => 'datetime',
        'passing_score' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (AdmissionTest $test): void {
            if (! $test->study_program_id) {
                return;
            }

            $program = StudyProgram::query()->find($test->study_program_id);

            if (! $program || $program->unit_id !== (int) $test->unit_id) {
                throw ValidationException::withMessages([
                    'study_program_id' => 'Program studi tes tidak sesuai dengan unit/institusi yang dipilih.',
                ]);
            }
        });
    }

    public function unit() { return $this->belongsTo(Unit::class); }
    public function studyProgram() { return $this->belongsTo(StudyProgram::class); }
    public function results() { return $this->hasMany(AdmissionTestResult::class); }
}
