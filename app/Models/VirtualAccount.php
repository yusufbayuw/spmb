<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualAccount extends Model
{
    use HasFactory;

    public const STATUSES = [
        'available' => 'Tersedia',
        'assigned' => 'Terpasang',
        'paid' => 'Lunas',
        'expired' => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'batch_id', 'unit_id', 'bank', 'va_number', 'amount', 'status', 'registration_id',
        'assigned_by', 'assigned_at', 'expired_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'assigned_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function batch() { return $this->belongsTo(VirtualAccountBatch::class, 'batch_id'); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function registration() { return $this->belongsTo(Registration::class); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
    public function payment() { return $this->hasOne(Payment::class); }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', 'available')
            ->whereNull('registration_id')
            ->where(function (Builder $query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            });
    }
}
