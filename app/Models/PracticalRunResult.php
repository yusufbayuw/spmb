<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalRunResult extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'practical_run_id', 'practical_assertion_id', 'passed', 'score',
        'expected', 'actual', 'feedback',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'score' => 'decimal:2',
        'expected' => 'array',
        'actual' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PracticalRun::class, 'practical_run_id');
    }

    public function assertion(): BelongsTo
    {
        return $this->belongsTo(PracticalAssertion::class, 'practical_assertion_id');
    }
}
