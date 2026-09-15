<?php

namespace Database\Seeders;

use Database\Seeders\Support\SpmbRolePermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUnitRoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (SpmbRolePermissions::required() as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }

            $adminUnit = Role::firstOrCreate([
                'name' => 'admin_unit',
                'guard_name' => 'web',
            ]);

            $tu = Role::firstOrCreate([
                'name' => 'tu',
                'guard_name' => 'web',
            ]);

            // Intentionally touch only role-permission relations. Existing users,
            // role assignments, registrations, payments, and other business data
            // are left unchanged.
            $adminUnit->syncPermissions(SpmbRolePermissions::adminUnit());
            $tu->syncPermissions(SpmbRolePermissions::tu());

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
