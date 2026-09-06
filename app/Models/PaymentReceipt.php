<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PaymentReceipt extends Model
{
    use HasPublicUuid;

    protected $fillable = ['payment_id', 'number', 'details', 'issued_at'];

    protected $casts = ['details' => 'array', 'issued_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['receipt' => 'Kuitansi yang telah diterbitkan tidak dapat diubah.']));
        static::deleting(fn () => throw ValidationException::withMessages(['receipt' => 'Kuitansi yang telah diterbitkan tidak dapat dihapus.']));
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
