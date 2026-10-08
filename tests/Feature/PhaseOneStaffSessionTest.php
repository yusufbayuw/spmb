<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneStaffSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_staff_sessions_are_revoked_after_credential_generation_changes(): void
    {
        $unit = Unit::create(['name' => 'Security Unit', 'code' => 'SEC', 'is_active' => true]);
        Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        $staff = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'auth_version' => 1]);
        $staff->assignRole('admin_unit');

        $this->actingAs($staff)
            ->withSession(['spmb_staff_auth_version' => 0])
            ->get('/admin')
            ->assertRedirect(route('login'));
    }

    public function test_rotation_changes_staff_password_and_invalidates_previous_session_generation(): void
    {
        Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        $staff = User::factory()->create(['auth_version' => 0]);
        $staff->assignRole('tu');
        $previousHash = $staff->password;

        $this->artisan('spmb:harden-staff-passwords', ['--execute' => true])
            ->expectsConfirmation('Rotate all staff passwords, revoke sessions, and require password recovery?', 'yes')
            ->assertExitCode(0);

        $staff->refresh();
        $this->assertNotSame($previousHash, $staff->password);
        $this->assertSame(1, $staff->auth_version);
    }

    public function test_password_rotation_command_defaults_to_preview(): void
    {
        Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        $staff = User::factory()->create(['auth_version' => 0]);
        $staff->assignRole('tu');

        $original = $staff->password;

        $this->artisan('spmb:harden-staff-passwords')->assertExitCode(0);

        $this->assertSame($original, $staff->fresh()->password);
        $this->assertSame(0, $staff->fresh()->auth_version);
    }
}
