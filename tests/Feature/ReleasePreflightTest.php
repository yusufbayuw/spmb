<?php

namespace Tests\Feature;

use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleasePreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_preflight_is_read_only(): void
    {
        $unit = Unit::create(['name' => 'Test School', 'code' => 'PRE', 'is_active' => true]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $this->artisan('spmb:release:preflight', ['--profile' => 'development'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'Test School',
        ]);
        $this->assertSame(1, Unit::count());
    }

    public function test_staging_preflight_passes_when_configured_for_its_environment(): void
    {
        config()->set([
            'app.env' => 'staging',
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
            'app.debug' => false,
            'app.url' => 'https://staging.example.test',
            'session.driver' => 'database',
            'session.secure' => true,
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.from.address' => 'noreply@example.test',
            'spmb.uploads.require_malware_scan' => true,
        ]);

        $this->artisan('spmb:release:preflight', ['--profile' => 'staging'])
            ->assertExitCode(0);
    }

    public function test_production_profile_rejects_debug_http_fake_mail_and_reset_allowlist(): void
    {
        config()->set([
            'app.env' => 'production',
            'app.key' => 'base64:'.base64_encode(str_repeat('c', 32)),
            'app.debug' => true,
            'app.url' => 'http://localhost',
            'session.driver' => 'array',
            'session.secure' => false,
            'queue.default' => 'sync',
            'mail.default' => 'log',
            'mail.from.address' => 'hello@example.com',
            'spmb.reset.allowed_target' => 'wrongly-enabled',
        ]);

        $this->artisan('spmb:release:preflight', ['--profile' => 'production'])
            ->assertExitCode(1);
    }

    public function test_preflight_rejects_publicly_served_applicant_storage(): void
    {
        config()->set([
            'app.key' => 'base64:'.base64_encode(str_repeat('d', 32)),
            'filesystems.disks.applicant-private.serve' => true,
        ]);

        $this->artisan('spmb:release:preflight', ['--profile' => 'development'])
            ->assertExitCode(1);
    }

    public function test_invalid_profile_fails_without_modifying_database(): void
    {
        $this->artisan('spmb:release:preflight', ['--profile' => 'unknown'])
            ->assertExitCode(1);
    }
}
