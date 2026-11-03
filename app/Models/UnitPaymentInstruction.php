<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitPaymentInstruction extends Model
{
    protected $fillable = ['unit_id', 'content'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
