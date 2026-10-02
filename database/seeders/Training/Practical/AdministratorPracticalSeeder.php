<?php

namespace Database\Seeders\Training\Practical;

use App\Models\CertificationProgram;
use App\Services\PracticalValidators\EventExistsValidator;
use App\Services\PracticalValidators\EventNotExistsValidator;
use App\Services\PracticalValidators\StateEqualsValidator;
use App\Services\PracticalValidators\StateUnchangedValidator;
use Database\Seeders\Training\CurriculumSeeder;

class AdministratorPracticalSeeder extends CurriculumSeeder
{
    public function run(): void
    {
        $program = CertificationProgram::query()->where('code', 'SCA')->firstOrFail();

        $this->accessScenario($program);
        $this->unitLifecycleScenario($program);
        $this->auditScenario($program);
        $this->recoveryScenario($program);
        $this->privacyScenario($program);
    }

    private function accessScenario(CertificationProgram $program): void
    {
        $s = $this->seedScenario($program, [
            'code'=>'SCA-ACCESS-01','name'=>'Perbaiki Akses Tanpa Privilege Berlebihan',
            'description'=>'Memperbaiki assignment staff dengan prinsip least privilege.',
            'instructions'=>'Seorang TU tidak dapat mengakses unitnya karena assignment unit belum benar. Pulihkan akses tanpa menaikkan role menjadi Super Admin dan tanpa menonaktifkan unit.',
            'time_limit_minutes'=>15,'sort_order'=>1,
        ]);
        $this->seedScenarioRecord($s,'staff','operator-a','Operator TU',['role'=>'tu','unit_assigned'=>false,'can_access_unit'=>false],1);
        $this->seedScenarioRecord($s,'unit','unit-a','Unit A',['status'=>'active'],2);
        $this->seedScenarioAction($s,'assign_unit','Tetapkan Unit yang Benar','staff','operator-a',['unit_assigned'=>true,'can_access_unit'=>true],[],'success',1);
        $this->seedScenarioAction($s,'grant_super_admin','Naikkan Menjadi Super Admin','staff','operator-a',['role'=>'super_admin','can_access_unit'=>true],[],'danger',2);
        $this->seedScenarioAction($s,'deactivate_unit','Nonaktifkan Unit','unit','unit-a',['status'=>'inactive'],[],'danger',3);
        $this->seedScenarioAssertion($s,'access-restored','Akses unit dipulihkan',StateEqualsValidator::class,['entity_type'=>'staff','entity_key'=>'operator-a','path'=>'can_access_unit','expected'=>true],30,true,1);
        $this->seedScenarioAssertion($s,'role-preserved','Role TU tetap dipertahankan',StateEqualsValidator::class,['entity_type'=>'staff','entity_key'=>'operator-a','path'=>'role','expected'=>'tu'],30,true,2);
        $this->seedScenarioAssertion($s,'unit-active','Unit tetap aktif',StateEqualsValidator::class,['entity_type'=>'unit','entity_key'=>'unit-a','path'=>'status','expected'=>'active'],20,true,3);
        $this->seedScenarioAssertion($s,'no-super-admin','Tidak memberikan privilege Super Admin',EventNotExistsValidator::class,['action_code'=>'grant_super_admin'],20,true,4);
    }

    private function unitLifecycleScenario(CertificationProgram $program): void
    {
        $s = $this->seedScenario($program, [
            'code'=>'SCA-UNIT-01','name'=>'Nonaktifkan Unit Secara Aman',
            'description'=>'Menonaktifkan unit setelah proses aktif ditutup tanpa menghapus histori.',
            'instructions'=>'Unit lama akan berhenti beroperasi. Masih ada dua opening aktif. Tutup proses aktif terlebih dahulu lalu nonaktifkan unit tanpa hard delete.',
            'time_limit_minutes'=>15,'sort_order'=>2,
        ]);
        $this->seedScenarioRecord($s,'unit','legacy-unit','Unit Lama',['status'=>'active','active_openings'=>2,'deleted'=>false,'history_count'=>420],1);
        $this->seedScenarioAction($s,'close_openings','Tutup Opening Aktif','unit','legacy-unit',['active_openings'=>0],['status'=>'active'],'warning',1);
        $this->seedScenarioAction($s,'deactivate_unit','Nonaktifkan Unit','unit','legacy-unit',['status'=>'inactive'],['active_openings'=>0],'success',2);
        $this->seedScenarioAction($s,'hard_delete_unit','Hard Delete Unit','unit','legacy-unit',['deleted'=>true,'history_count'=>0],[],'danger',3);
        $this->seedScenarioAssertion($s,'inactive','Unit menjadi inactive',StateEqualsValidator::class,['entity_type'=>'unit','entity_key'=>'legacy-unit','path'=>'status','expected'=>'inactive'],30,true,1);
        $this->seedScenarioAssertion($s,'openings-closed','Tidak ada opening aktif',StateEqualsValidator::class,['entity_type'=>'unit','entity_key'=>'legacy-unit','path'=>'active_openings','expected'=>0],25,true,2);
        $this->seedScenarioAssertion($s,'history-preserved','Histori dipertahankan',StateUnchangedValidator::class,['entity_type'=>'unit','entity_key'=>'legacy-unit','path'=>'history_count'],25,true,3);
        $this->seedScenarioAssertion($s,'no-hard-delete','Tidak melakukan hard delete',EventNotExistsValidator::class,['action_code'=>'hard_delete_unit'],20,true,4);
    }

    private function auditScenario(CertificationProgram $program): void
    {
        $s = $this->seedScenario($program, [
            'code'=>'SCA-AUDIT-01','name'=>'Investigasi Perubahan Konfigurasi',
            'description'=>'Menggunakan audit evidence tanpa mengubah atau menghapus log.',
            'instructions'=>'Konfigurasi payment berubah tanpa rencana. Audit menunjukkan event oleh user admin-unit-b. Identifikasi actor yang benar dan pertahankan log.',
            'time_limit_minutes'=>12,'sort_order'=>3,
        ]);
        $this->seedScenarioRecord($s,'incident','payment-config','Insiden Konfigurasi',['actor_identified'=>null,'audit_logs_preserved'=>true,'event_count'=>4],1);
        $this->seedScenarioAction($s,'identify_admin_b','Identifikasi admin-unit-b','incident','payment-config',['actor_identified'=>'admin-unit-b'],[],'success',1);
        $this->seedScenarioAction($s,'identify_tu_a','Identifikasi tu-a','incident','payment-config',['actor_identified'=>'tu-a'],[],'warning',2);
        $this->seedScenarioAction($s,'delete_logs','Hapus Audit Log','incident','payment-config',['audit_logs_preserved'=>false,'event_count'=>0],[],'danger',3);
        $this->seedScenarioAssertion($s,'actor','Actor diidentifikasi berdasarkan evidence',StateEqualsValidator::class,['entity_type'=>'incident','entity_key'=>'payment-config','path'=>'actor_identified','expected'=>'admin-unit-b'],40,true,1);
        $this->seedScenarioAssertion($s,'logs-preserved','Audit log dipertahankan',StateEqualsValidator::class,['entity_type'=>'incident','entity_key'=>'payment-config','path'=>'audit_logs_preserved','expected'=>true],30,true,2);
        $this->seedScenarioAssertion($s,'no-delete','Tidak menghapus audit log',EventNotExistsValidator::class,['action_code'=>'delete_logs'],30,true,3);
    }

    private function recoveryScenario(CertificationProgram $program): void
    {
        $s = $this->seedScenario($program, [
            'code'=>'SCA-RECOVERY-01','name'=>'Pilih Recovery yang Proporsional',
            'description'=>'Memulihkan salah konfigurasi tanpa menimpa data transaksi baru.',
            'instructions'=>'Aplikasi sehat tetapi konfigurasi global salah setelah perubahan. Ada 240 registration baru sejak backup terakhir. Pulihkan konfigurasi tanpa restore database penuh.',
            'time_limit_minutes'=>12,'sort_order'=>4,
        ]);
        $this->seedScenarioRecord($s,'service','portal','Portal SPMB',['health'=>'degraded','config_valid'=>false,'registration_count'=>240],1);
        $this->seedScenarioAction($s,'rollback_config','Rollback Konfigurasi','service','portal',['health'=>'healthy','config_valid'=>true],[],'success',1);
        $this->seedScenarioAction($s,'restore_database','Restore Database Lama','service','portal',['health'=>'healthy','config_valid'=>true,'registration_count'=>180],[],'danger',2);
        $this->seedScenarioAssertion($s,'healthy','Layanan kembali sehat',StateEqualsValidator::class,['entity_type'=>'service','entity_key'=>'portal','path'=>'health','expected'=>'healthy'],30,true,1);
        $this->seedScenarioAssertion($s,'config-valid','Konfigurasi kembali valid',StateEqualsValidator::class,['entity_type'=>'service','entity_key'=>'portal','path'=>'config_valid','expected'=>true],25,true,2);
        $this->seedScenarioAssertion($s,'data-preserved','Registration baru tidak hilang',StateUnchangedValidator::class,['entity_type'=>'service','entity_key'=>'portal','path'=>'registration_count'],30,true,3);
        $this->seedScenarioAssertion($s,'no-db-restore','Tidak melakukan restore database yang tidak proporsional',EventNotExistsValidator::class,['action_code'=>'restore_database'],15,true,4);
    }

    private function privacyScenario(CertificationProgram $program): void
    {
        $s = $this->seedScenario($program, [
            'code'=>'SCA-PRIVACY-01','name'=>'Contain Cross-Unit Data Exposure',
            'description'=>'Membatasi akses yang salah tanpa memperluas paparan atau menghapus evidence.',
            'instructions'=>'Admin Unit A dapat melihat data seluruh unit akibat scope salah. Batasi akses kembali ke Unit A. Jangan export seluruh data dan jangan hapus log.',
            'time_limit_minutes'=>12,'sort_order'=>5,
        ]);
        $this->seedScenarioRecord($s,'staff','admin-unit-a','Admin Unit A',['access_scope'=>'all-units','audit_logs_preserved'=>true,'bulk_exported'=>false],1);
        $this->seedScenarioAction($s,'restrict_scope','Batasi ke Unit A','staff','admin-unit-a',['access_scope'=>'unit-a'],[],'success',1);
        $this->seedScenarioAction($s,'export_all','Export Semua Data sebagai Bukti','staff','admin-unit-a',['bulk_exported'=>true],[],'danger',2);
        $this->seedScenarioAction($s,'delete_logs','Hapus Log Akses','staff','admin-unit-a',['audit_logs_preserved'=>false],[],'danger',3);
        $this->seedScenarioAssertion($s,'scope-fixed','Scope kembali ke Unit A',StateEqualsValidator::class,['entity_type'=>'staff','entity_key'=>'admin-unit-a','path'=>'access_scope','expected'=>'unit-a'],40,true,1);
        $this->seedScenarioAssertion($s,'no-export','Tidak memperluas paparan melalui bulk export',EventNotExistsValidator::class,['action_code'=>'export_all'],30,true,2);
        $this->seedScenarioAssertion($s,'logs-preserved','Log tetap dipertahankan',StateEqualsValidator::class,['entity_type'=>'staff','entity_key'=>'admin-unit-a','path'=>'audit_logs_preserved','expected'=>true],30,true,3);
    }
}
