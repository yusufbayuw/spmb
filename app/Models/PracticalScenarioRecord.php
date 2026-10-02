<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalScenarioRecord extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'practical_scenario_id', 'entity_type', 'entity_key', 'label', 'initial_state', 'sort_order',
    ];

    protected $casts = [
        'initial_state' => 'array',
        'sort_order' => 'integer',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(PracticalScenario::class, 'practical_scenario_id');
    }
}
