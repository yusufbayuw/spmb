<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    public function test_manifest_defines_installable_application_scope_and_icons(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('SPMB Taruna Bakti', $manifest['name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);

        $this->assertSame([192, 192], array_slice(getimagesize(public_path('images/pwa/icon-192.png')), 0, 2));
        $this->assertSame([512, 512], array_slice(getimagesize(public_path('images/pwa/icon-512.png')), 0, 2));
        $this->assertSame([512, 512], array_slice(getimagesize(public_path('images/pwa/icon-maskable-512.png')), 0, 2));
        $this->assertSame([180, 180], array_slice(getimagesize(public_path('images/pwa/apple-touch-icon.png')), 0, 2));
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
    }

    public function test_service_worker_handles_push_without_caching_authenticated_pages(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('push'", $serviceWorker);
        $this->assertStringContainsString("addEventListener('notificationclick'", $serviceWorker);
        $this->assertStringNotContainsString("addEventListener('fetch'", $serviceWorker);
        $this->assertStringNotContainsString('caches.open', $serviceWorker);
    }

    public function test_filament_auth_pages_load_pwa_manifest_and_client(): void
    {
        foreach (['/admin/login', '/pendaftar/login', '/pendaftar/register'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('manifest.webmanifest', false)
                ->assertSee('js/pwa.js', false)
                ->assertSee('spmb-pwa-config', false);
        }
    }
}
