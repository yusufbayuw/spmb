<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\VirtualAccountImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VirtualAccountImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_import_accepts_va_bank_and_unit_pipe_format(): void
    {
        Storage::fake('local');

        $sma = Unit::create(['name' => 'Sekolah Menengah Atas', 'code' => 'SMA', 'is_active' => true]);
        $smp = Unit::create(['name' => 'Sekolah Menengah Pertama', 'code' => 'SMP', 'is_active' => true]);
        $staff = $this->staffWithRole('super_admin');

        $path = 'imports/virtual-accounts/pool.txt';
        Storage::disk('local')->put($path, "8432572985 | MANDIRI | SMA\n8432572986 | BCA | SMP\n");

        $result = app(VirtualAccountImportService::class)->importFile($path, $staff);

        $this->assertSame(2, $result['total']);
        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['failed']);
        $this->assertFalse(Schema::hasColumn('virtual_accounts', 'amount'));

        $this->assertDatabaseHas('virtual_accounts', [
            'va_number' => '8432572985',
            'bank' => 'MANDIRI',
            'unit_id' => $sma->id,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('virtual_accounts', [
            'va_number' => '8432572986',
            'bank' => 'BCA',
            'unit_id' => $smp->id,
            'status' => 'available',
        ]);
    }

    public function test_tu_cannot_import_virtual_account_for_another_unit(): void
    {
        Storage::fake('local');

        $sma = Unit::create(['name' => 'Sekolah Menengah Atas', 'code' => 'SMA', 'is_active' => true]);
        Unit::create(['name' => 'Sekolah Menengah Pertama', 'code' => 'SMP', 'is_active' => true]);
        $staff = $this->staffWithRole('tu', ['unit_id' => $sma->id, 'role' => 'tu']);

        $path = 'imports/virtual-accounts/pool.txt';
        Storage::disk('local')->put($path, "8432572985 | MANDIRI | SMP\n");

        $result = app(VirtualAccountImportService::class)->importFile($path, $staff);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['failed']);
        $this->assertFalse(VirtualAccount::query()->where('va_number', '8432572985')->exists());
    }

    private function staffWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create($attributes + [
            'role' => 'user',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
