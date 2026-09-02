<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $standard = ['view','view_any','create','update','delete','delete_any'];
        $resources = ['registration','registrationopening','parentinfo','document','payment','virtualaccount','unit','user','admissiontest','admissiontestresult','selection','announcement','auditlog'];
        foreach ($resources as $resource) {
            foreach ($standard as $prefix) Permission::firstOrCreate(['name' => $prefix.'_'.$resource,'guard_name' => 'web']);
        }
        foreach (['validate_data_registration','send_va_registration','issue_card_registration','verify_payment_payment','verify_document_document','record_result_admissiontestresult','decide_selection','publish_announcement'] as $permission) {
            Permission::firstOrCreate(['name' => $permission,'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super_admin','guard_name' => 'web']);
        $tu = Role::firstOrCreate(['name' => 'tu','guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'pendaftar','guard_name' => 'web']);

        $tuPermissions = [
            'view_registration','view_any_registration','update_registration','validate_data_registration','send_va_registration','issue_card_registration',
            'view_registrationopening','view_any_registrationopening','create_registrationopening','update_registrationopening',
            'view_parentinfo','view_any_parentinfo','update_parentinfo',
            'view_document','view_any_document','update_document','verify_document_document',
            'view_payment','view_any_payment','create_payment','update_payment','verify_payment_payment',
            'view_virtualaccount','view_any_virtualaccount','create_virtualaccount','update_virtualaccount',
            'view_unit','view_any_unit',
            'view_admissiontest','view_any_admissiontest','create_admissiontest','update_admissiontest','delete_admissiontest',
            'view_admissiontestresult','view_any_admissiontestresult','create_admissiontestresult','update_admissiontestresult','record_result_admissiontestresult',
            'view_selection','view_any_selection','create_selection','update_selection','decide_selection',
            'view_announcement','view_any_announcement','create_announcement','update_announcement','publish_announcement',
            'view_auditlog','view_any_auditlog',
        ];
        $tu->syncPermissions($tuPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
