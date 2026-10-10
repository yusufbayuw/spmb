<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Auth\StaffProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_accessible_to_staff_but_not_applicants(): void
    {
        $this->get('/admin/profile')->assertRedirect();

        foreach (['super_admin', 'admin_unit', 'tu'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/admin/profile')
                ->assertOk()
                ->assertSee('Profil Saya');

            $this->get('/profile')->assertRedirect('/admin/profile');
        }

        $this->actingAs($this->userWithRole('pendaftar'))
            ->get('/admin/profile')
            ->assertForbidden();

        $this->get('/profile')->assertRedirect('/pendaftar/profile');
    }

    public function test_staff_can_update_own_name_without_rotating_credentials(): void
    {
        $staff = $this->userWithRole('tu', ['auth_version' => 2]);
        $beforePassword = $staff->password;
        $beforeToken = $staff->getRememberToken();

        $this->actingAs($staff)->withSession(['spmb_staff_auth_version' => 2]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StaffProfile::class)
            ->fillForm(['name' => 'Nama Diperbarui'])
            ->call('save')
            ->assertHasNoFormErrors();

        $staff->refresh();
        $this->assertSame('Nama Diperbarui', $staff->name);
        $this->assertSame($beforePassword, $staff->password);
        $this->assertSame($beforeToken, $staff->getRememberToken());
        $this->assertSame(2, $staff->auth_version);
    }

    public function test_staff_can_change_password_with_current_password_and_revoke_old_sessions(): void
    {
        $staff = $this->userWithRole('admin_unit', [
            'password' => Hash::make('CurrentPassword123!'),
            'auth_version' => 2,
        ]);
        $oldHash = $staff->password;
        $oldToken = $staff->getRememberToken();

        $this->actingAs($staff)->withSession(['spmb_staff_auth_version' => 2]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StaffProfile::class)
            ->fillForm([
                'name' => 'Admin Unit',
                'current_password' => 'CurrentPassword123!',
                'password' => 'FreshPassword567!',
                'passwordConfirmation' => 'FreshPassword567!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $staff->refresh();
        $this->assertNotSame($oldHash, $staff->password);
        $this->assertTrue(Hash::check('FreshPassword567!', $staff->password));
        $this->assertSame(3, $staff->auth_version);
        $this->assertNotSame($oldToken, $staff->getRememberToken());

        $this->withSession(['spmb_staff_auth_version' => 2])
            ->get('/admin')
            ->assertRedirect(route('login'));
    }

    public function test_current_password_is_required_and_must_be_valid_for_changes(): void
    {
        $staff = $this->userWithRole('super_admin', [
            'password' => Hash::make('CurrentPassword123!'),
        ]);
        $original = $staff->password;

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StaffProfile::class)
            ->fillForm([
                'password' => 'FreshPassword567!',
                'passwordConfirmation' => 'FreshPassword567!',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        Livewire::test(StaffProfile::class)
            ->fillForm([
                'current_password' => 'WrongPassword123!',
                'password' => 'FreshPassword567!',
                'passwordConfirmation' => 'FreshPassword567!',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        $this->assertSame($original, $staff->fresh()->password);
        $this->assertSame(0, $staff->fresh()->auth_version);
    }

    public function test_confirmation_and_password_policy_are_enforced(): void
    {
        $staff = $this->userWithRole('tu', [
            'password' => Hash::make('CurrentPassword123!'),
        ]);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StaffProfile::class)
            ->fillForm([
                'current_password' => 'CurrentPassword123!',
                'password' => 'FreshPassword567!',
                'passwordConfirmation' => 'DifferentPassword456!',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);

        Livewire::test(StaffProfile::class)
            ->fillForm([
                'current_password' => 'CurrentPassword123!',
                'password' => '123',
                'passwordConfirmation' => '123',
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_profile_never_updates_identity_and_permissions(): void
    {
        $staff = $this->userWithRole('tu', ['email' => 'tu@example.test', 'username' => 'tu.awal']);

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StaffProfile::class)
            ->fillForm(['name' => 'Nama Aman'])
            ->set('data.email', 'attacker@example.test')
            ->set('data.username', 'owner')
            ->set('data.role', 'super_admin')
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $staff->refresh();
        $this->assertSame('Nama Aman', $staff->name);
        $this->assertSame('tu@example.test', $staff->email);
        $this->assertSame('tu.awal', $staff->username);
        $this->assertTrue($staff->is_active);
        $this->assertTrue($staff->hasRole('tu'));
        $this->assertFalse($staff->hasRole('super_admin'));
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create($attributes + [
            'is_active' => true,
            'role' => $role === 'pendaftar' ? 'user' : $role,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
