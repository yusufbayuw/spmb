<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticalScenario extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'certification_program_id', 'code', 'name', 'description', 'instructions',
        'time_limit_minutes', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'time_limit_minutes' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(CertificationProgram::class, 'certification_program_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(PracticalScenarioRecord::class)->orderBy('sort_order')->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(PracticalScenarioAction::class)->orderBy('sort_order')->orderBy('id');
    }

    public function assertions(): HasMany
    {
        return $this->hasMany(PracticalAssertion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PracticalRun::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
