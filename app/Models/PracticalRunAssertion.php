<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PracticalRunAssertion extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'practical_run_id', 'practical_assertion_id', 'code', 'name',
        'validator_class', 'config', 'points', 'is_critical', 'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'points' => 'decimal:2',
        'is_critical' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PracticalRun::class, 'practical_run_id');
    }

    public function sourceAssertion(): BelongsTo
    {
        return $this->belongsTo(PracticalAssertion::class, 'practical_assertion_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(PracticalRunResult::class, 'practical_run_assertion_id');
    }
}
