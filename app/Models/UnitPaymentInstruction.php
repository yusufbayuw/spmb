<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitPaymentInstruction extends Model
{
    protected $fillable = [
        'unit_id', 'content',
        'show_on_payment_page', 'show_on_next_action', 'show_on_payment_summary',
    ];

    protected $casts = [
        'show_on_payment_page' => 'boolean',
        'show_on_next_action' => 'boolean',
        'show_on_payment_summary' => 'boolean',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
