<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalRunAction extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'practical_run_id', 'practical_scenario_action_id', 'code', 'label',
        'target_type', 'target_key', 'mutation', 'allowed_when', 'button_color',
        'requires_confirmation', 'sort_order',
    ];

    protected $casts = [
        'mutation' => 'array',
        'allowed_when' => 'array',
        'requires_confirmation' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PracticalRun::class, 'practical_run_id');
    }

    public function sourceAction(): BelongsTo
    {
        return $this->belongsTo(PracticalScenarioAction::class, 'practical_scenario_action_id');
    }
}
