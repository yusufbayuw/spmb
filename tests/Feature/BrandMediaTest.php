<?php

namespace Tests\Feature;

use App\Services\BrandMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_media_uses_same_origin_route_for_existing_unit_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put(
            'units/logos/unit.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M/wHwAF/gL+X8WnWQAAAABJRU5ErkJggg=='),
        );

        $metadata = app(BrandMediaService::class)->fileMetadata('units/logos/unit.png');

        $this->assertNotNull($metadata);
        $this->assertSame('unit.png', $metadata['name']);
        $this->assertSame('/media/branding/units/logos/unit.png', $metadata['url']);

        $this->get($metadata['url'])
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_brand_media_rejects_non_branding_paths(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('other/private.txt', 'secret');

        $this->assertNull(app(BrandMediaService::class)->fileMetadata('other/private.txt'));
        $this->get('/media/branding/other/private.txt')->assertNotFound();
    }
}
