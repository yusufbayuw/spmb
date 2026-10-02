<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalScenarioAction extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'practical_scenario_id', 'code', 'label', 'target_type', 'target_key',
        'mutation', 'allowed_when', 'button_color', 'requires_confirmation',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'mutation' => 'array',
        'allowed_when' => 'array',
        'requires_confirmation' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(PracticalScenario::class, 'practical_scenario_id');
    }
}
