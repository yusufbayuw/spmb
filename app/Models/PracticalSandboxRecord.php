<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalSandboxRecord extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'practical_run_id', 'practical_scenario_record_id', 'entity_type', 'entity_key',
        'label', 'original_state', 'state',
    ];

    protected $casts = [
        'original_state' => 'array',
        'state' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PracticalRun::class, 'practical_run_id');
    }

    public function scenarioRecord(): BelongsTo
    {
        return $this->belongsTo(PracticalScenarioRecord::class, 'practical_scenario_record_id');
    }
}
