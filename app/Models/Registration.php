<?php

namespace App\Models;

use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Registration extends Model
{
    use HasFactory;

    public const STAGES = [
        'data_validation' => 'Validasi Data',
        'virtual_account' => 'Penerbitan Virtual Account',
        'payment' => 'Pembayaran Formulir',
        'payment_verification' => 'Verifikasi Pembayaran',
        'applicant_card' => 'Kartu Pendaftar',
        'documents' => 'Melengkapi Berkas',
        'document_verification' => 'Verifikasi Berkas',
        'tests' => 'Rangkaian Tes',
        'selection' => 'Seleksi Calon Siswa',
        'announcement' => 'Pengumuman',
        'completed' => 'Selesai',
    ];

    /**
     * The only legal workflow transitions. A transition to the same stage is
     * allowed for in-stage updates (for example a validation revision).
     */
    public const STAGE_TRANSITIONS = [
        'data_validation' => ['virtual_account'],
        'virtual_account' => ['payment'],
        'payment' => ['payment_verification'],
        'payment_verification' => ['payment', 'applicant_card'],
        'applicant_card' => ['documents'],
        'documents' => ['document_verification'],
        'document_verification' => ['documents', 'tests', 'selection'],
        'tests' => ['selection'],
        'selection' => ['announcement'],
        'announcement' => ['completed'],
        'completed' => [],
    ];

    protected $fillable = [
        'user_id','unit_id','registration_opening_id','registrant_type','registrant_relationship','registration_number','nik','full_name','nickname','gender','birth_place','birth_date','religion','child_order','siblings_count','home_address','rt','rw','village','district','city','province','postal_code','phone','email','previous_school','previous_school_address','graduation_year','status','current_stage','data_validation_status','data_validation_notes','data_validated_by','data_validated_at','applicant_card_number','applicant_card_issued_by','applicant_card_issued_at','documents_completed_at','documents_verified_at','rejection_reason','submitted_at','verified_at','payment_verified_at','accepted_at',
    ];

    protected $casts = [
        'birth_date' => 'date','submitted_at' => 'datetime','verified_at' => 'datetime','payment_verified_at' => 'datetime','accepted_at' => 'datetime','data_validated_at' => 'datetime','applicant_card_issued_at' => 'datetime','documents_completed_at' => 'datetime','documents_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Registration $registration): void {
            if (blank($registration->registration_number) && $registration->unit) {
                $registration->forceFill(['registration_number' => $registration->generateRegistrationNumber()])->saveQuietly();
            }
        });
    }

    public function user() { return $this->belongsTo(User::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function opening() { return $this->belongsTo(RegistrationOpening::class, 'registration_opening_id'); }
    public function parentInfo() { return $this->hasOne(ParentInfo::class); }
    public function documents() { return $this->hasMany(Document::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function latestPayment() { return $this->hasOne(Payment::class)->latestOfMany(); }
    public function virtualAccount() { return $this->hasOne(VirtualAccount::class); }
    public function testResults() { return $this->hasMany(AdmissionTestResult::class); }
    public function selection() { return $this->hasOne(Selection::class); }
    public function announcement() { return $this->hasOne(Announcement::class); }
    public function dataValidator() { return $this->belongsTo(User::class, 'data_validated_by'); }
    public function cardIssuer() { return $this->belongsTo(User::class, 'applicant_card_issued_by'); }

    public function generateRegistrationNumber(): string
    {
        $year = $this->opening?->academic_year
            ? str_replace(['/', '-'], '', $this->opening->academic_year)
            : ($this->created_at?->format('Y') ?? now()->format('Y'));

        return 'REG-'.$this->unit->code.'-'.$year.'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function generateApplicantCardNumber(): string
    {
        $year = now()->format('Y');
        return 'KARTU-'.$this->unit->code.'-'.$year.'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->current_stage] ?? $this->current_stage;
    }

    public function canTransitionTo(string $targetStage): bool
    {
        if (! array_key_exists($targetStage, self::STAGES)) {
            return false;
        }

        if ($this->current_stage === $targetStage) {
            return true;
        }

        return in_array(
            $targetStage,
            self::STAGE_TRANSITIONS[$this->current_stage] ?? [],
            true,
        );
    }

    public function assertCurrentStage(string|array $expectedStages): void
    {
        $expectedStages = (array) $expectedStages;

        if (in_array($this->current_stage, $expectedStages, true)) {
            return;
        }

        $expectedLabels = collect($expectedStages)
            ->map(fn (string $stage): string => self::STAGES[$stage] ?? $stage)
            ->implode(' / ');

        throw ValidationException::withMessages([
            'current_stage' => "Tahap pendaftaran sudah berubah. Proses ini hanya dapat dilakukan pada tahap {$expectedLabels}. Tahap saat ini: {$this->stageLabel()}.",
        ]);
    }

    /**
     * Transition atomically from the model's current stage. The conditional
     * update also protects against stale/concurrent requests. Because query
     * updates intentionally bypass Eloquent observers, the transition writes
     * its own immutable audit entry after a successful compare-and-swap.
     */
    public function transitionTo(string $targetStage, array $attributes = []): void
    {
        $sourceStage = (string) $this->current_stage;

        if (! $this->canTransitionTo($targetStage)) {
            $sourceLabel = self::STAGES[$sourceStage] ?? $sourceStage;
            $targetLabel = self::STAGES[$targetStage] ?? $targetStage;

            throw ValidationException::withMessages([
                'current_stage' => "Perpindahan tahap {$sourceLabel} → {$targetLabel} tidak diizinkan.",
            ]);
        }

        $oldValues = ['current_stage' => $sourceStage];
        foreach (array_keys($attributes) as $key) {
            $oldValues[$key] = $this->getRawOriginal($key);
        }

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('current_stage', $sourceStage)
            ->update(array_merge($attributes, ['current_stage' => $targetStage]));

        if ($updated !== 1) {
            $this->refresh();

            throw ValidationException::withMessages([
                'current_stage' => 'Tahap pendaftaran berubah oleh proses lain. Muat ulang data sebelum melanjutkan.',
            ]);
        }

        $this->refresh();

        $newValues = ['current_stage' => $targetStage];
        foreach (array_keys($attributes) as $key) {
            $newValues[$key] = $this->getRawOriginal($key);
        }

        app(AuditTrail::class)->record(
            'registration.stage_transition',
            $this,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: [
                'from_stage' => $sourceStage,
                'to_stage' => $targetStage,
            ],
            description: (self::STAGES[$sourceStage] ?? $sourceStage).' → '.(self::STAGES[$targetStage] ?? $targetStage),
        );
    }
}
