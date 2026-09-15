<?php

namespace Database\Seeders;

use Database\Seeders\Support\SpmbRolePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $standard = ['view', 'view_any', 'create', 'update', 'delete', 'delete_any'];
        $resources = [
            'registration', 'registrationopening', 'registrationpathway', 'studyprogram', 'parentinfo', 'document',
            'payment', 'virtualaccount', 'unit', 'user', 'admissiontest', 'admissiontestresult',
            'selection', 'selectionbatch', 'admissionquota', 'admissionoffer', 'reregistrationitem', 'announcement', 'auditlog',
        ];

        foreach ($resources as $resource) {
            foreach ($standard as $prefix) {
                Permission::firstOrCreate(['name' => $prefix.'_'.$resource, 'guard_name' => 'web']);
            }
        }

        foreach ([
            'validate_data_registration', 'send_va_registration', 'issue_card_registration',
            'verify_payment_payment', 'verify_document_document', 'record_result_admissiontestresult',
            'decide_selection', 'publish_announcement', 'finalize_selectionbatch', 'enroll_registration',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminUnit = Role::firstOrCreate(['name' => 'admin_unit', 'guard_name' => 'web']);
        $tu = Role::firstOrCreate(['name' => 'tu', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'pendaftar', 'guard_name' => 'web']);

        $adminUnit->syncPermissions(SpmbRolePermissions::adminUnit());
        $tu->syncPermissions(SpmbRolePermissions::tu());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
