<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AppSetting extends Model
{
    protected $fillable = [
        'portal_name',
        'organization_name',
        'website_url',
        'email',
        'phone',
        'whatsapp',
        'address',
        'service_hours',
        'logo_path',
        'theme_color',
        'training_enforce_sequence',
        'training_require_module_mastery',
        'certification_enforcement_mode',
        'certification_expiry_reminder_days',
        'certificate_artifact_enabled',
    ];

    protected $casts = [
        'training_enforce_sequence' => 'boolean',
        'training_require_module_mastery' => 'boolean',
        'certification_expiry_reminder_days' => 'array',
        'certificate_artifact_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AppSetting $setting): void {
            $setting->uuid ??= (string) Str::uuid();
        });
    }
}
