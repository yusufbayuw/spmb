<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationAchievement extends Model
{
    protected $fillable = [
        'registration_id',
        'title',
        'level',
        'year',
        'organizer',
        'description',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
