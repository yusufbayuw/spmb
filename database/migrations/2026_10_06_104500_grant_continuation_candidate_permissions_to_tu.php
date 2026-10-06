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

        $tu = Role::query()
            ->where('name', 'tu')
            ->where('guard_name', 'web')
            ->first();

        if ($tu) {
            $tu->givePermissionTo(self::PERMISSIONS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tu = Role::query()
            ->where('name', 'tu')
            ->where('guard_name', 'web')
            ->first();

        if ($tu) {
            foreach (self::PERMISSIONS as $permission) {
                $tu->revokePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
