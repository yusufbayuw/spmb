<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AppBrandingService
{
    private bool $loaded = false;

    private ?AppSetting $settings = null;

    public function settings(): ?AppSetting
    {
        if ($this->loaded) {
            return $this->settings;
        }

        $this->loaded = true;

        try {
            if (! Schema::hasTable('app_settings')) {
                return null;
            }

            $this->settings = AppSetting::query()->first();
        } catch (Throwable) {
            $this->settings = null;
        }

        return $this->settings;
    }

    public function refresh(): void
    {
        $this->loaded = false;
        $this->settings = null;
    }

    /** @return array<string, mixed> */
    public function effective(): array
    {
        $fallback = config('spmb.portal', []);
        $settings = $this->settings();

        if (! $settings) {
            return [
                'name' => $fallback['name'] ?? config('app.name', 'SPMB'),
                'foundation_name' => $fallback['foundation_name'] ?? config('app.name', 'Institusi Pendidikan'),
                'foundation_website' => $fallback['foundation_website'] ?? null,
                'foundation_email' => $fallback['foundation_email'] ?? null,
                'foundation_phone' => $fallback['foundation_phone'] ?? null,
                'foundation_whatsapp' => $fallback['foundation_whatsapp'] ?? null,
                'foundation_address' => $fallback['foundation_address'] ?? null,
                'service_hours' => $fallback['service_hours'] ?? null,
                'logo_path' => $fallback['logo_path'] ?? null,
                'theme_color' => $fallback['theme_color'] ?? '#2563eb',
            ];
        }

        return [
            'name' => $settings->portal_name,
            'foundation_name' => $settings->organization_name,
            'foundation_website' => $settings->website_url,
            'foundation_email' => $settings->email,
            'foundation_phone' => $settings->phone,
            'foundation_whatsapp' => $settings->whatsapp,
            'foundation_address' => $settings->address,
            'service_hours' => $settings->service_hours,
            'logo_path' => $settings->logo_path,
            'theme_color' => $settings->theme_color ?: '#2563eb',
        ];
    }

    public function portalName(): string
    {
        return (string) ($this->effective()['name'] ?: 'SPMB');
    }

    public function organizationName(): string
    {
        return (string) ($this->effective()['foundation_name'] ?: 'Institusi Pendidikan');
    }

    public function themeColor(): string
    {
        return (string) ($this->effective()['theme_color'] ?: '#2563eb');
    }

    public function logoUrl(): ?string
    {
        $effective = $this->effective();
        $path = $effective['logo_path'] ?? null;

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'branding/')) {
            return app(BrandMediaService::class)->url($path);
        }

        return asset($path);
    }

    public function applyToConfig(): void
    {
        config(['spmb.portal' => array_replace(config('spmb.portal', []), $this->effective())]);
    }
}
