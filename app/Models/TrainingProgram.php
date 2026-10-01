<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    use HasPublicUuid;

    public const ROLE_LABELS = [
        'super_admin' => 'Admin Pusat',
        'admin_unit' => 'Admin Unit',
        'tu' => 'TU / Operator',
    ];

    protected $fillable = [
        'code', 'name', 'description', 'target_role', 'version', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function modules(): HasMany
    {
        return $this->hasMany(TrainingModule::class)->orderBy('sort_order')->orderBy('id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    public function certificationPrograms(): HasMany
    {
        return $this->hasMany(CertificationProgram::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereIn('target_role', $user->getRoleNames()->all());
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->target_role] ?? $this->target_role;
    }
}
