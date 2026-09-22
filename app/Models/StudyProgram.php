<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Support\SpmbOperationalMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
        'workflow_steps',
    ];

    protected $casts = [
        'max_age' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'workflow_steps' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (StudyProgram $program): void {
            if (! SpmbOperationalMode::allowsHigherEducation()) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Program studi tidak tersedia pada mode operasional K12.',
                ]);
            }

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

            if (Schema::hasColumn('study_programs', 'workflow_steps')) {
                $program->workflow_steps = static::normalizeWorkflowSteps($program->workflow_steps);
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

    public function scopeForOperationalMode(Builder $query): Builder
    {
        return $query->whereHas('unit', fn (Builder $unitQuery): Builder => $unitQuery->forOperationalMode());
    }

    public function isAllowedByOperationalMode(): bool
    {
        return SpmbOperationalMode::allowsHigherEducation()
            && ($this->unit?->isAllowedByOperationalMode() ?? false);
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

    /**
     * @return list<array{stage:string,label:string,description:string,visible:bool}>
     */
    public static function defaultWorkflowSteps(): array
    {
        return [
            ['stage' => 'data_validation', 'label' => 'Validasi Data', 'description' => 'Pemeriksaan data identitas calon mahasiswa.', 'visible' => true],
            ['stage' => 'virtual_account', 'label' => 'Penerbitan Virtual Account', 'description' => 'Penerbitan nomor Virtual Account untuk pembayaran formulir.', 'visible' => true],
            ['stage' => 'payment', 'label' => 'Pembayaran Formulir', 'description' => 'Pembayaran biaya formulir pendaftaran.', 'visible' => true],
            ['stage' => 'payment_verification', 'label' => 'Verifikasi Pembayaran Formulir', 'description' => 'Pemeriksaan pembayaran formulir oleh petugas.', 'visible' => true],
            ['stage' => 'applicant_card', 'label' => 'Kartu Pendaftar', 'description' => 'Kartu pendaftar diterbitkan setelah persyaratan awal terpenuhi.', 'visible' => true],
            ['stage' => 'documents', 'label' => 'Melengkapi Berkas', 'description' => 'Calon mahasiswa melengkapi dokumen sesuai ketentuan program studi.', 'visible' => true],
            ['stage' => 'document_verification', 'label' => 'Verifikasi Berkas', 'description' => 'Petugas memeriksa kelengkapan dan validitas berkas.', 'visible' => true],
            ['stage' => 'tests', 'label' => 'Rangkaian Tes', 'description' => 'Calon mahasiswa mengikuti tes yang diwajibkan.', 'visible' => true],
            ['stage' => 'selection', 'label' => 'Seleksi Calon Mahasiswa', 'description' => 'Hasil tes dan persyaratan diproses dalam tahap seleksi.', 'visible' => true],
            ['stage' => 'announcement', 'label' => 'Pengumuman', 'description' => 'Hasil penerimaan diumumkan kepada calon mahasiswa.', 'visible' => true],
            ['stage' => 'waiting_list', 'label' => 'Daftar Tunggu', 'description' => 'Tahap ini hanya tampil untuk calon mahasiswa yang berada pada daftar tunggu.', 'visible' => true],
            ['stage' => 'admission_offer', 'label' => 'Pembayaran Registrasi', 'description' => 'Konfirmasi penerimaan dan kewajiban registrasi sesuai kebijakan program studi.', 'visible' => true],
            ['stage' => 're_registration', 'label' => 'Daftar Ulang', 'description' => 'Pemenuhan persyaratan daftar ulang yang ditetapkan perguruan tinggi.', 'visible' => true],
            ['stage' => 'enrollment', 'label' => 'Perwalian', 'description' => 'Tahap administrasi awal mahasiswa sebelum proses akademik dimulai.', 'visible' => true],
            ['stage' => 'completed', 'label' => 'Selesai', 'description' => 'Seluruh rangkaian penerimaan pada program studi telah selesai.', 'visible' => true],
        ];
    }

    /**
     * @param  array<int, mixed>|null  $steps
     * @return list<array{stage:string,label:string,description:string,visible:bool}>
     */
    public static function normalizeWorkflowSteps(?array $steps): array
    {
        $defaults = collect(static::defaultWorkflowSteps())->keyBy('stage');
        $normalized = collect($steps ?? [])
            ->filter(fn ($step): bool => is_array($step) && isset($step['stage']) && $defaults->has($step['stage']))
            ->map(function (array $step) use ($defaults): array {
                $default = $defaults->get($step['stage']);

                return [
                    'stage' => $step['stage'],
                    'label' => filled($step['label'] ?? null) ? trim((string) $step['label']) : $default['label'],
                    'description' => trim((string) ($step['description'] ?? $default['description'])),
                    'visible' => (bool) ($step['visible'] ?? true),
                ];
            })
            ->unique('stage')
            ->values();

        foreach ($defaults as $stage => $default) {
            if (! $normalized->contains('stage', $stage)) {
                $normalized->push($default);
            }
        }

        return $normalized->values()->all();
    }

    /**
     * @return list<array{stage:string,label:string,description:string,visible:bool}>
     */
    public function configuredWorkflowSteps(): array
    {
        return static::normalizeWorkflowSteps($this->workflow_steps);
    }

    /**
     * @return array{stage:string,label:string,description:string,visible:bool}|null
     */
    public function workflowStep(string $stage): ?array
    {
        return collect($this->configuredWorkflowSteps())
            ->first(fn (array $step): bool => $step['stage'] === $stage);
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
