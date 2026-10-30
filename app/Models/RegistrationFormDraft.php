<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationFormDraft extends Model
{
    protected $fillable = [
        'user_id',
        'registration_opening_id',
        'unit_configuration_id',
        'payload',
        'revision',
    ];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'revision' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(RegistrationOpening::class, 'registration_opening_id');
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(UnitConfiguration::class, 'unit_configuration_id');
    }
}
