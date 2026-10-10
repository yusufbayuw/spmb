<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ProductionReadinessCenter;
use App\Jobs\ProductionReadinessHeartbeat;
use App\Models\User;
use App\Services\ProductionReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionReadinessCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin_unit', 'tu', 'pendaftar'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_only_active_super_admin_can_open_the_readiness_gui(): void
    {
        $admin = $this->staff('super_admin');
        $this->actingAs($admin)->get('/admin/production-readiness-center')->assertOk()
            ->assertSee('BELUM SIAP PRODUCTION');
        $this->actingAs($this->staff('admin_unit'))->get('/admin/production-readiness-center')->assertForbidden();
        $this->actingAs($this->staff('tu'))->get('/admin/production-readiness-center')->assertForbidden();
        $this->actingAs($this->staff('pendaftar'))->get('/admin/production-readiness-center')->assertForbidden();
        $admin->forceFill(['is_active' => false])->save();
        $this->actingAs($admin)->get('/admin/production-readiness-center')->assertForbidden();
    }

    public function test_read_only_report_does_not_create_or_mutate_application_records(): void
    {
        $service = app(ProductionReadinessService::class);
        // Copy-paste deployment requires no release SHA or Git directory.
        $before = DB::table('production_readiness_attestations')->count();
        $snapshotCount = DB::table('production_readiness_snapshots')->count();

        $report = $service->report();

        $this->assertSame('not_ready', $report['status']);
        $this->assertGreaterThan(0, $report['failures']);
        $this->assertSame(count(ProductionReadinessService::MANUAL), $report['pending']);
        $this->assertSame($before, DB::table('production_readiness_attestations')->count());
        $this->assertSame($snapshotCount, DB::table('production_readiness_snapshots')->count());
    }

    public function test_manual_audit_evidence_persists_across_copy_paste_deployments(): void
    {
        $service = app(ProductionReadinessService::class);
        $admin = $this->staff('super_admin');

        $this->assertSame('installation', $service->auditScope());
        $this->assertSame('installation', $service->report()['audit_scope']);

        try {
            $service->attest($admin, 'backup_restore', 'pass', 'OK');
            $this->fail('Bukti singkat tidak cukup.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('evidence', $exception->errors());
        }

        $service->attest($admin, 'backup_restore', 'pass', 'Tiket DR-2026-10: restore file dan database berhasil.');
        $this->assertDatabaseHas('production_readiness_attestations', [
            'release_sha' => 'installation', 'check_id' => 'backup_restore',
            'status' => 'pass', 'reviewed_by' => $admin->id,
        ]);
        $report = $service->report();
        $this->assertSame('pass', collect($report['checks'])->firstWhere('id', 'backup_restore')['status']);

        // Even a stale SHA left in the old .env no longer affects readiness.
        config()->set('spmb.readiness.release_sha', str_repeat('a', 40));
        $this->assertSame('pass', collect($service->report()['checks'])->firstWhere('id', 'backup_restore')['status']);
        config()->set('spmb.readiness.release_sha', str_repeat('b', 40));
        $this->assertSame('pass', collect($service->report()['checks'])->firstWhere('id', 'backup_restore')['status']);

        $service->attest($admin, 'backup_restore', 'pending', 'Perlu periksa ulang setelah perubahan penting.');
        $this->assertSame('pending', collect($service->report()['checks'])->firstWhere('id', 'backup_restore')['status']);
    }

    public function test_staff_cannot_create_attestations_or_snapshot_and_snapshot_is_audited(): void
    {
        $service = app(ProductionReadinessService::class);

        $tu = $this->staff('tu');
        try {
            $service->snapshot($tu);
            $this->fail('TU tidak boleh menyimpan snapshot.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $admin = $this->staff('super_admin');
        $report = $service->snapshot($admin);

        $this->assertSame('not_ready', $report['status']);
        $this->assertDatabaseHas('production_readiness_snapshots', [
            'release_sha' => 'installation', 'status' => 'not_ready',
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'release.readiness_snapshot']);
    }

    public function test_worker_heartbeat_records_execution_for_each_queue(): void
    {
        Cache::forget('spmb:readiness:worker:mail');
        Cache::forget('spmb:readiness:worker:notifications');

        (new ProductionReadinessHeartbeat('mail'))->handle();
        (new ProductionReadinessHeartbeat('notifications'))->handle();

        $this->assertIsInt(Cache::get('spmb:readiness:worker:mail'));
        $this->assertIsInt(Cache::get('spmb:readiness:worker:notifications'));
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['is_active' => true, 'role' => $role]);
        $user->assignRole($role);

        return $user;
    }
}
