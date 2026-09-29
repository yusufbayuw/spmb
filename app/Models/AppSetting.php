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
    ];

    protected static function booted(): void
    {
        static::creating(function (AppSetting $setting): void {
            $setting->uuid ??= (string) Str::uuid();
        });
    }
}
