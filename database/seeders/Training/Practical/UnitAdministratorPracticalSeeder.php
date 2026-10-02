<?php

namespace Database\Seeders\Training\Practical;

use App\Models\CertificationProgram;
use App\Services\PracticalValidators\EventExistsValidator;
use App\Services\PracticalValidators\EventNotExistsValidator;
use App\Services\PracticalValidators\StateEqualsValidator;
use App\Services\PracticalValidators\StateUnchangedValidator;
use Database\Seeders\Training\CurriculumSeeder;

class UnitAdministratorPracticalSeeder extends CurriculumSeeder
{
    public function run(): void
    {
        $program = CertificationProgram::query()->where('code', 'SCUA')->where('version', '1.0')->firstOrFail();

        $this->openingScenario($program);
        $this->configScenario($program);
        $this->workflowScenario($program);
        $this->documentScenario($program);
        $this->paymentScenario($program);
        $this->testScenario($program);
        $this->selectionScenario($program);
        $this->reportScenario($program);
    }

    private function openingScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCUA-OPENING-01','name'=>'Pause Pendaftaran Tanpa Merusak Data','description'=>'Menghentikan sementara penerimaan secara aman.','instructions'=>'Gelombang 1 sedang open dengan 12 pendaftar. Hentikan sementara sehingga tidak terlihat pendaftar, jangan close permanen dan jangan ubah data pendaftar.','time_limit_minutes'=>10,'sort_order'=>1]);
        $this->seedScenarioRecord($s,'opening','gelombang-1','Gelombang 1 · 2027/2028',['status'=>'open','visible_to_applicants'=>true,'registration_count'=>12],1);
        $this->seedScenarioAction($s,'pause_opening','Pause Pendaftaran','opening','gelombang-1',['status'=>'paused','visible_to_applicants'=>false],['status'=>'open'],'warning',1);
        $this->seedScenarioAction($s,'close_opening','Tutup Pendaftaran','opening','gelombang-1',['status'=>'closed','visible_to_applicants'=>false],['status'=>'open'],'danger',2);
        $this->seedScenarioAction($s,'delete_registrations','Hapus Data Pendaftar','opening','gelombang-1',['registration_count'=>0],[],'danger',3);
        $this->seedScenarioAssertion($s,'paused','Status menjadi paused',StateEqualsValidator::class,['entity_type'=>'opening','entity_key'=>'gelombang-1','path'=>'status','expected'=>'paused'],35,true,1);
        $this->seedScenarioAssertion($s,'hidden','Tidak terlihat oleh pendaftar',StateEqualsValidator::class,['entity_type'=>'opening','entity_key'=>'gelombang-1','path'=>'visible_to_applicants','expected'=>false],25,true,2);
        $this->seedScenarioAssertion($s,'data-preserved','Jumlah pendaftar tetap',StateUnchangedValidator::class,['entity_type'=>'opening','entity_key'=>'gelombang-1','path'=>'registration_count'],25,true,3);
        $this->seedScenarioAssertion($s,'no-delete','Tidak menghapus data',EventNotExistsValidator::class,['action_code'=>'delete_registrations'],15,true,4);
    }

    private function configScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCUA-CONFIG-01','name'=>'Publikasikan Konfigurasi yang Siap','description'=>'Memastikan hanya draft valid yang dipublikasikan.','instructions'=>'Draft konfigurasi telah lolos validasi. Publikasikan secara normal agar visible ke pendaftar. Jangan gunakan force publish.','time_limit_minutes'=>10,'sort_order'=>2]);
        $this->seedScenarioRecord($s,'configuration','unit-config','Konfigurasi Unit',['status'=>'draft','validation_errors'=>0,'public_visible'=>false],1);
        $this->seedScenarioAction($s,'publish_config','Publish Konfigurasi','configuration','unit-config',['status'=>'published','public_visible'=>true],['validation_errors'=>0,'status'=>'draft'],'success',1);
        $this->seedScenarioAction($s,'force_publish','Force Publish','configuration','unit-config',['status'=>'published','public_visible'=>true],[],'danger',2);
        $this->seedScenarioAssertion($s,'published','Status published',StateEqualsValidator::class,['entity_type'=>'configuration','entity_key'=>'unit-config','path'=>'status','expected'=>'published'],35,true,1);
        $this->seedScenarioAssertion($s,'visible','Terlihat pendaftar',StateEqualsValidator::class,['entity_type'=>'configuration','entity_key'=>'unit-config','path'=>'public_visible','expected'=>true],30,true,2);
        $this->seedScenarioAssertion($s,'no-force','Tidak menggunakan force publish',EventNotExistsValidator::class,['action_code'=>'force_publish'],35,true,3);
    }

    private function workflowScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCUA-WORKFLOW-01','name'=>'Perbaiki Workflow Tidak Valid','description'=>'Memperbaiki urutan tahap tanpa menghilangkan requirement penting.','instructions'=>'Workflow menempatkan payment sebelum data validation dan ditandai invalid. Perbaiki urutan tanpa menghapus tahap wajib.','time_limit_minutes'=>12,'sort_order'=>3]);
        $this->seedScenarioRecord($s,'workflow','main','Workflow Utama',['valid'=>false,'order'=>'payment>data_validation>documents>tests','required_stages_preserved'=>true],1);
        $this->seedScenarioAction($s,'reorder_workflow','Susun Ulang Workflow','workflow','main',['valid'=>true,'order'=>'data_validation>documents>payment>tests'],[],'success',1);
        $this->seedScenarioAction($s,'remove_payment_stage','Hapus Tahap Payment','workflow','main',['valid'=>true,'order'=>'data_validation>documents>tests','required_stages_preserved'=>false],[],'danger',2);
        $this->seedScenarioAssertion($s,'valid','Workflow menjadi valid',StateEqualsValidator::class,['entity_type'=>'workflow','entity_key'=>'main','path'=>'valid','expected'=>true],40,true,1);
        $this->seedScenarioAssertion($s,'required','Tahap wajib dipertahankan',StateEqualsValidator::class,['entity_type'=>'workflow','entity_key'=>'main','path'=>'required_stages_preserved','expected'=>true],35,true,2);
        $this->seedScenarioAssertion($s,'no-remove','Tidak menghapus payment stage',EventNotExistsValidator::class,['action_code'=>'remove_payment_stage'],25,true,3);
    }

    private function documentScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCUA-DOCUMENT-01','name'=>'Minimalkan Persyaratan Dokumen','description'=>'Menghapus requirement yang tidak punya dasar tanpa mengubah requirement inti.','instructions'=>'KK dan rapor memang wajib. Surat kesehatan terlanjur diwajibkan padahal tidak diperlukan kebijakan. Nonaktifkan requirement surat kesehatan saja.','time_limit_minutes'=>10,'sort_order'=>4]);
        $this->seedScenarioRecord($s,'document_config','requirements','Persyaratan Dokumen',['kk_required'=>true,'rapor_required'=>true,'medical_required'=>true],1);
        $this->seedScenarioAction($s,'remove_medical','Nonaktifkan Surat Kesehatan','document_config','requirements',['medical_required'=>false],[],'success',1);
        $this->seedScenarioAction($s,'remove_rapor','Nonaktifkan Rapor','document_config','requirements',['rapor_required'=>false],[],'danger',2);
        $this->seedScenarioAssertion($s,'medical-off','Requirement tidak perlu dinonaktifkan',StateEqualsValidator::class,['entity_type'=>'document_config','entity_key'=>'requirements','path'=>'medical_required','expected'=>false],40,true,1);
        $this->seedScenarioAssertion($s,'kk-stays','KK tetap wajib',StateUnchangedValidator::class,['entity_type'=>'document_config','entity_key'=>'requirements','path'=>'kk_required'],30,true,2);
        $this->seedScenarioAssertion($s,'rapor-stays','Rapor tetap wajib',StateUnchangedValidator::class,['entity_type'=>'document_config','entity_key'=>'requirements','path'=>'rapor_required'],30,true,3);
    }

    private function paymentScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCUA-PAYMENT-01','name'=>'Pilih VA Pool yang Tepat','description'=>'Menggunakan fallback resmi tanpa meminjam pool program lain.','instructions'=>'Pool Program A habis. General pool masih memiliki nomor. Program B memiliki pool sendiri. Pilih general pool untuk Program A; jangan pinjam pool Program B.','time_limit_minutes'=>10,'sort_order'=>5]);
        $this->seedScenarioRecord($s,'va_assignment','applicant-a','Assignment VA',['program'=>'A','program_a_available'=>0,'general_available'=>5,'program_b_available'=>10,'assigned_source'=>null],1);
        $this->seedScenarioAction($s,'assign_general','Gunakan General Pool','va_assignment','applicant-a',['assigned_source'=>'general'],['general_available'=>5],'success',1);
        $this->seedScenarioAction($s,'borrow_program_b','Pinjam Pool Program B','va_assignment','applicant-a',['assigned_source'=>'program-b'],[],'danger',2);
        $this->seedScenarioAssertion($s,'general','Sumber VA adalah general pool',StateEqualsValidator::class,['entity_type'=>'va_assignment','entity_key'=>'applicant-a','path'=>'assigned_source','expected'=>'general'],60,true,1);
        $this->seedScenarioAssertion($s,'no-borrow','Tidak meminjam pool Program B',EventNotExistsValidator::class,['action_code'=>'borrow_program_b'],40,true,2);
    }

    private function testScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCUA-TEST-01','name'=>'Tangani Sesi Tes Penuh','description'=>'Memindahkan kebutuhan ke sesi tersedia tanpa overbooking.','instructions'=>'Sesi pagi penuh. Sesi siang tersedia. Tempatkan peserta pada sesi siang tanpa menaikkan kapasitas secara tidak sah.','time_limit_minutes'=>10,'sort_order'=>6]);
        $this->seedScenarioRecord($s,'test_booking','candidate-1','Booking Tes',['morning_full'=>true,'afternoon_available'=>true,'booked_session'=>null],1);
        $this->seedScenarioAction($s,'book_afternoon','Pilih Sesi Siang','test_booking','candidate-1',['booked_session'=>'afternoon'],['afternoon_available'=>true],'success',1);
        $this->seedScenarioAction($s,'force_morning','Paksa Sesi Pagi','test_booking','candidate-1',['booked_session'=>'morning-overbooked'],[],'danger',2);
        $this->seedScenarioAssertion($s,'afternoon','Peserta ditempatkan di sesi tersedia',StateEqualsValidator::class,['entity_type'=>'test_booking','entity_key'=>'candidate-1','path'=>'booked_session','expected'=>'afternoon'],60,true,1);
        $this->seedScenarioAssertion($s,'no-overbook','Tidak melakukan overbooking',EventNotExistsValidator::class,['action_code'=>'force_morning'],40,true,2);
    }

    private function selectionScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCUA-SELECTION-01','name'=>'Review Sebelum Publikasi Hasil','description'=>'Mencegah publikasi hasil sebelum quality check selesai.','instructions'=>'Batch hasil masih draft dan review belum selesai. Lakukan review lalu publish. Jangan publish sebelum review.','time_limit_minutes'=>12,'sort_order'=>7]);
        $this->seedScenarioRecord($s,'selection_batch','batch-1','Batch Seleksi',['status'=>'draft','review_complete'=>false,'public_visible'=>false],1);
        $this->seedScenarioAction($s,'complete_review','Selesaikan Review','selection_batch','batch-1',['review_complete'=>true],['status'=>'draft'],'primary',1);
        $this->seedScenarioAction($s,'publish_reviewed','Publish Hasil','selection_batch','batch-1',['status'=>'published','public_visible'=>true],['review_complete'=>true],'success',2);
        $this->seedScenarioAction($s,'publish_unreviewed','Publish Tanpa Review','selection_batch','batch-1',['status'=>'published','public_visible'=>true],['review_complete'=>false],'danger',3);
        $this->seedScenarioAssertion($s,'published','Batch dipublikasikan',StateEqualsValidator::class,['entity_type'=>'selection_batch','entity_key'=>'batch-1','path'=>'status','expected'=>'published'],30,true,1);
        $this->seedScenarioAssertion($s,'reviewed','Review selesai',StateEqualsValidator::class,['entity_type'=>'selection_batch','entity_key'=>'batch-1','path'=>'review_complete','expected'=>true],30,true,2);
        $this->seedScenarioAssertion($s,'visible','Hasil terlihat pendaftar',StateEqualsValidator::class,['entity_type'=>'selection_batch','entity_key'=>'batch-1','path'=>'public_visible','expected'=>true],20,true,3);
        $this->seedScenarioAssertion($s,'no-early-publish','Tidak publish sebelum review',EventNotExistsValidator::class,['action_code'=>'publish_unreviewed'],20,true,4);
    }

    private function reportScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCUA-REPORT-01','name'=>'Ekspor Laporan Sesuai Scope Unit','description'=>'Menjaga export tetap dalam scope dan kebutuhan kerja.','instructions'=>'Anda Admin Unit A dan perlu laporan operasional unit sendiri. Export hanya Unit A. Jangan export seluruh unit.','time_limit_minutes'=>8,'sort_order'=>8]);
        $this->seedScenarioRecord($s,'report_request','operational','Permintaan Laporan',['requested_scope'=>'unit-a','export_scope'=>null,'all_units_exported'=>false],1);
        $this->seedScenarioAction($s,'export_unit_a','Export Unit A','report_request','operational',['export_scope'=>'unit-a'],[],'success',1);
        $this->seedScenarioAction($s,'export_all_units','Export Semua Unit','report_request','operational',['export_scope'=>'all-units','all_units_exported'=>true],[],'danger',2);
        $this->seedScenarioAssertion($s,'correct-scope','Export hanya Unit A',StateEqualsValidator::class,['entity_type'=>'report_request','entity_key'=>'operational','path'=>'export_scope','expected'=>'unit-a'],60,true,1);
        $this->seedScenarioAssertion($s,'no-global','Tidak export seluruh unit',EventNotExistsValidator::class,['action_code'=>'export_all_units'],40,true,2);
    }
}
