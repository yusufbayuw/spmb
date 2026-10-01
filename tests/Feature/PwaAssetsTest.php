<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaAssetsTest extends TestCase
{
    use RefreshDatabase;
    public function test_manifest_defines_installable_application_scope_and_icons(): void
    {
        $response = $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8');

        $manifest = $response->json();

        $this->assertSame(config('spmb.portal.name', 'SPMB'), $manifest['name']);
        $this->assertSame('/dashboard', $manifest['start_url']);
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

    public function test_global_file_preview_modal_is_fail_closed_even_without_tailwind_hidden_utility(): void
    {
        $view = file_get_contents(resource_path('views/components/file-preview-modal.blade.php'));

        $this->assertStringContainsString('aria-hidden="true"', $view);
        $this->assertStringContainsString('#file-preview-modal[aria-hidden="true"]', $view);
        $this->assertStringContainsString('display: none !important;', $view);
        $this->assertStringContainsString('#file-preview-modal[aria-hidden="false"]', $view);
        $this->assertStringContainsString('display: block !important;', $view);
    }

    public function test_unified_login_and_applicant_registration_load_pwa_manifest_and_client(): void
    {
        foreach (['/login', '/pendaftar/register'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('manifest.webmanifest', false)
                ->assertSee('js/pwa.js', false)
                ->assertSee('spmb-pwa-config', false);
        }

        $this->get('/login')
            ->assertSee('fi-simple-page', false)
            ->assertDontSee('Satu halaman masuk untuk pendaftar, TU, Admin Unit, dan Super Admin.');

        $this->get('/admin/login')->assertRedirect('/login');
        $this->get('/pendaftar/login')->assertRedirect('/login');
    }
}
