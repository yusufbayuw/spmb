<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class UnitConfiguration extends Model
{
    use HasPublicUuid;

    protected $fillable = ['unit_id', 'version', 'status', 'payment_enabled', 'documents_enabled', 'tests_enabled', 'selection_mode', 'post_announcement_enabled', 'builtin_field_policy', 'academic_scores_enabled', 'academic_score_settings', 'achievements_enabled', 'achievement_settings', 'fields', 'document_requirements', 'test_definitions', 're_registration_requirements', 'published_at', 'legacy'];

    protected $casts = ['payment_enabled' => 'boolean', 'documents_enabled' => 'boolean', 'tests_enabled' => 'boolean', 'post_announcement_enabled' => 'boolean', 'academic_scores_enabled' => 'boolean', 'academic_score_settings' => 'array', 'achievements_enabled' => 'boolean', 'achievement_settings' => 'array', 'fields' => 'array', 'document_requirements' => 'array', 'test_definitions' => 'array', 're_registration_requirements' => 'array', 'published_at' => 'datetime', 'legacy' => 'boolean'];

    protected static function booted(): void
    {
        static::updating(function (self $configuration): void {
            if ($configuration->getOriginal('status') === 'published') {
                throw ValidationException::withMessages(['configuration' => 'Versi terpublikasi tidak dapat diubah. Buat draft versi baru.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['configuration' => 'Konfigurasi tidak dapat dihapus.']));
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
