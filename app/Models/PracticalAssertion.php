<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalAssertion extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'practical_scenario_id', 'code', 'name', 'validator_class', 'config',
        'points', 'is_critical', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'points' => 'decimal:2',
        'is_critical' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(PracticalScenario::class, 'practical_scenario_id');
    }
}
