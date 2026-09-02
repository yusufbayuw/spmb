<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'unit_id',
        'registration_number',
        'nik',
        'full_name',
        'nickname',
        'gender',
        'birth_place',
        'birth_date',
        'religion',
        'child_order',
        'siblings_count',
        'home_address',
        'rt',
        'rw',
        'village',
        'district',
        'city',
        'province',
        'postal_code',
        'phone',
        'email',
        'previous_school',
        'previous_school_address',
        'graduation_year',
        'status',
        'rejection_reason',
        'submitted_at',
        'verified_at',
        'payment_verified_at',
        'accepted_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'payment_verified_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Registration $registration): void {
            if (blank($registration->registration_number) && $registration->unit) {
                $registration->forceFill([
                    'registration_number' => $registration->generateRegistrationNumber(),
                ])->saveQuietly();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function parentInfo()
    {
        return $this->hasOne(ParentInfo::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function generateRegistrationNumber(): string
    {
        $year = $this->created_at?->format('Y') ?? now()->format('Y');
        $unitCode = $this->unit->code;
        $sequence = str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);

        return "REG-{$unitCode}-{$year}-{$sequence}";
    }
}
