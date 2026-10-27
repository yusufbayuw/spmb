<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class AdmissionTest extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $fillable = [
        'unit_id', 'study_program_id', 'study_program_ids', 'name', 'code', 'description', 'sort_order',
        'is_required', 'is_active', 'scheduled_at', 'location', 'passing_score', 'result_type',
    ];

    protected $casts = [
        'study_program_ids' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'scheduled_at' => 'datetime',
        'passing_score' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (AdmissionTest $test): void {
            $programIds = $test->study_program_ids;

            if ($programIds !== null) {
                if (! is_array($programIds)) {
                    throw ValidationException::withMessages([
                        'study_program_ids' => 'Daftar program studi tidak valid.',
                    ]);
                }

                $normalized = array_values(array_unique(array_map('intval', $programIds)));
                sort($normalized);

                if (count($normalized) !== count($programIds) || in_array(0, $normalized, true)
                    || StudyProgram::query()->where('unit_id', $test->unit_id)
                        ->whereIn('id', $normalized)->count() !== count($normalized)) {
                    throw ValidationException::withMessages([
                        'study_program_ids' => 'Setiap program studi harus berasal dari institusi yang sama.',
                    ]);
                }

                $test->study_program_ids = $normalized;
            }

            if ($test->study_program_id) {
                $program = StudyProgram::query()->find($test->study_program_id);

                if (! $program || (int) $program->unit_id !== (int) $test->unit_id) {
                    throw ValidationException::withMessages([
                        'study_program_id' => 'Program studi tes tidak sesuai dengan unit/institusi yang dipilih.',
                    ]);
                }
            }

            // A live test with bookings/results must retain its original scope.
            // Versioned definitions remain pinned; this is an additional operational guard.
            if ($test->exists && $test->isDirty(['study_program_ids', 'study_program_id'])) {
                $previous = $test->getRawOriginal('study_program_ids');
                $previousIds = $previous === null
                    ? (($test->getRawOriginal('study_program_id') !== null)
                        ? [(int) $test->getRawOriginal('study_program_id')] : [])
                    : json_decode((string) $previous, true);
                $previousIds = array_map('intval', is_array($previousIds) ? $previousIds : []);
                $newIds = $test->effectiveProgramIds();
                sort($previousIds);

                if ($previousIds !== $newIds
                    && (TestBooking::query()->where('admission_test_id', $test->id)->exists()
                        || $test->results()->exists())) {
                    throw ValidationException::withMessages([
                        'study_program_ids' => 'Cakupan prodi tidak dapat diubah setelah tes memiliki booking atau hasil. Buat master tes baru.',
                    ]);
                }
            }
        });
    }

    /** @return list<int> Empty means all programs. */
    public function effectiveProgramIds(): array
    {
        $ids = $this->study_program_ids;

        if (is_array($ids)) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            sort($ids);

            return $ids;
        }

        return $this->study_program_id ? [(int) $this->study_program_id] : [];
    }

    public function appliesToStudyProgram(?int $studyProgramId): bool
    {
        $ids = $this->effectiveProgramIds();

        return $ids === [] || ($studyProgramId !== null && in_array($studyProgramId, $ids, true));
    }

    public function programScopeLabel(): string
    {
        $ids = $this->effectiveProgramIds();

        if ($ids === []) {
            return 'Semua program studi';
        }

        $names = StudyProgram::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();

        return $names !== [] ? implode(', ', $names) : 'Program studi tertentu';
    }


    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function studyProgram()
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function results()
    {
        return $this->hasMany(AdmissionTestResult::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TestSession::class);
    }
}
