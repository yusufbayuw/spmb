<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view_continuationcandidate',
        'view_any_continuationcandidate',
        'create_continuationcandidate',
        'update_continuationcandidate',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $adminUnit = Role::query()
            ->where('name', 'admin_unit')
            ->where('guard_name', 'web')
            ->first();

        if ($adminUnit) {
            $adminUnit->givePermissionTo(self::PERMISSIONS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminUnit = Role::query()
            ->where('name', 'admin_unit')
            ->where('guard_name', 'web')
            ->first();

        if ($adminUnit) {
            $adminUnit->revokePermissionTo(self::PERMISSIONS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
