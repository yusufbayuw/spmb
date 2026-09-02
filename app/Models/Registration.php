<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    protected $fillable = [
        'user_id','unit_id','registrant_type','registrant_relationship','registration_number','nik','full_name','nickname','gender','birth_place','birth_date','religion','child_order','siblings_count','home_address','rt','rw','village','district','city','province','postal_code','phone','email','previous_school','previous_school_address','graduation_year','status','current_stage','data_validation_status','data_validation_notes','data_validated_by','data_validated_at','applicant_card_number','applicant_card_issued_by','applicant_card_issued_at','documents_completed_at','documents_verified_at','rejection_reason','submitted_at','verified_at','payment_verified_at','accepted_at',
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
        $year = $this->created_at?->format('Y') ?? now()->format('Y');
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
}
