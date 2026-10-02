<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalRunEvent extends Model
{
    use HasPublicUuid;

    public $timestamps = false;

    protected $fillable = [
        'practical_run_id', 'practical_scenario_action_id', 'action_code',
        'target_type', 'target_key', 'before_state', 'after_state', 'metadata', 'created_at',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PracticalRun::class, 'practical_run_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(PracticalScenarioAction::class, 'practical_scenario_action_id');
    }
}
