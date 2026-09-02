<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationOpening extends Model
{
    use HasFactory;

    public const STATUSES = [
        'draft' => 'Draft',
        'open' => 'Dibuka',
        'closed' => 'Ditutup',
        'archived' => 'Diarsipkan',
    ];

    protected $fillable = [
        'unit_id',
        'academic_year',
        'wave',
        'pathway',
        'registration_fee',
        'description',
        'status',
        'opened_at',
        'closed_at',
        'archived_at',
        'created_by',
    ];

    protected $casts = [
        'registration_fee' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (RegistrationOpening $opening): void {
            $opening->created_by ??= auth()->id();
        });
    }

    public function unit() { return $this->belongsTo(Unit::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function registrations() { return $this->hasMany(Registration::class); }

    public function scopeVisibleToApplicants(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'closed']);
    }

    public function isOpen(): bool { return $this->status === 'open'; }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function formattedFee(): string
    {
        return 'Rp '.number_format((float) $this->registration_fee, 0, ',', '.');
    }

    public function label(): string
    {
        return implode(' · ', [
            $this->unit?->name ?? 'Unit',
            'TA '.$this->academic_year,
            $this->wave,
            'Jalur '.$this->pathway,
        ]);
    }

    public function open(): void
    {
        $this->update([
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
            'archived_at' => null,
        ]);
    }

    public function close(): void
    {
        $this->update(['status' => 'closed', 'closed_at' => now()]);
    }

    public function archive(): void
    {
        $this->update(['status' => 'archived', 'archived_at' => now()]);
    }
}
