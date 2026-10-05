<?php

namespace Database\Seeders\Training\Practical;

use App\Models\CertificationProgram;
use App\Services\PracticalValidators\EventNotExistsValidator;
use App\Services\PracticalValidators\StateEqualsValidator;
use Database\Seeders\Training\CurriculumSeeder;

class AdmissionOperatorPracticalSeeder extends CurriculumSeeder
{
    public function run(): void
    {
        $program = CertificationProgram::query()->where('code', 'SCAO')->where('version', '1.0')->firstOrFail();

        $this->verificationScenario($program);
        $this->identityScenario($program);
        $this->documentScenario($program);
        $this->paymentScenario($program);
        $this->testScenario($program);
        $this->escalationScenario($program);
        $this->testScheduleSupportScenario($program);
    }

    private function verificationScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCAO-VERIFY-01','name'=>'Verifikasi Berkas dan Pembayaran','description'=>'Mengukur ketelitian pada tiga keputusan operasional.','instructions'=>'Rapor valid, KK tidak valid, dan pembayaran sesuai nominal. Tetapkan status masing-masing item secara tepat.','time_limit_minutes'=>12,'sort_order'=>1]);
        $this->seedScenarioRecord($s,'document','rapor','Rapor',['status'=>'pending','valid'=>true],1);
        $this->seedScenarioRecord($s,'document','kk','Kartu Keluarga',['status'=>'pending','valid'=>false],2);
        $this->seedScenarioRecord($s,'payment','registration-fee','Pembayaran Pendaftaran',['status'=>'pending','amount_matches'=>true],3);
        $this->seedScenarioAction($s,'verify_rapor','Verifikasi Rapor','document','rapor',['status'=>'verified'],['status'=>'pending'],'success',1);
        $this->seedScenarioAction($s,'reject_rapor','Tolak Rapor','document','rapor',['status'=>'rejected'],['status'=>'pending'],'danger',2);
        $this->seedScenarioAction($s,'verify_kk','Verifikasi KK','document','kk',['status'=>'verified'],['status'=>'pending'],'danger',3);
        $this->seedScenarioAction($s,'reject_kk','Tolak KK','document','kk',['status'=>'rejected'],['status'=>'pending'],'warning',4);
        $this->seedScenarioAction($s,'verify_payment','Verifikasi Pembayaran','payment','registration-fee',['status'=>'verified'],['status'=>'pending'],'success',5);
        $this->seedScenarioAction($s,'reject_payment','Tolak Pembayaran','payment','registration-fee',['status'=>'rejected'],['status'=>'pending'],'danger',6);
        $this->seedScenarioAssertion($s,'rapor-ok','Rapor diverifikasi',StateEqualsValidator::class,['entity_type'=>'document','entity_key'=>'rapor','path'=>'status','expected'=>'verified'],30,true,1);
        $this->seedScenarioAssertion($s,'kk-rejected','KK tidak valid ditolak',StateEqualsValidator::class,['entity_type'=>'document','entity_key'=>'kk','path'=>'status','expected'=>'rejected'],40,true,2);
        $this->seedScenarioAssertion($s,'payment-ok','Pembayaran diverifikasi',StateEqualsValidator::class,['entity_type'=>'payment','entity_key'=>'registration-fee','path'=>'status','expected'=>'verified'],30,true,3);
    }

    private function identityScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCAO-IDENTITY-01','name'=>'Tangani Mismatch Identitas','description'=>'Menolak shortcut verifikasi ketika data dan dokumen tidak cocok.','instructions'=>'Nama pada data pendaftaran tidak sama dengan dokumen identitas. Tandai perlu koreksi; jangan verify identitas.','time_limit_minutes'=>8,'sort_order'=>2]);
        $this->seedScenarioRecord($s,'identity','candidate-1','Identitas Pendaftar',['status'=>'pending','name_matches_document'=>false],1);
        $this->seedScenarioAction($s,'request_correction','Minta Koreksi Data','identity','candidate-1',['status'=>'needs_correction'],['name_matches_document'=>false],'warning',1);
        $this->seedScenarioAction($s,'verify_identity','Verifikasi Identitas','identity','candidate-1',['status'=>'verified'],[],'danger',2);
        $this->seedScenarioAssertion($s,'correction','Status meminta koreksi',StateEqualsValidator::class,['entity_type'=>'identity','entity_key'=>'candidate-1','path'=>'status','expected'=>'needs_correction'],60,true,1);
        $this->seedScenarioAssertion($s,'no-verify','Tidak memverifikasi mismatch',EventNotExistsValidator::class,['action_code'=>'verify_identity'],40,true,2);
    }

    private function documentScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCAO-DOC-01','name'=>'Tolak Dokumen yang Tidak Terbaca','description'=>'Memberi keputusan tepat pada dokumen buram.','instructions'=>'Dokumen KK buram dan data utama tidak terbaca. Tolak dokumen; jangan verify.','time_limit_minutes'=>8,'sort_order'=>3]);
        $this->seedScenarioRecord($s,'document','kk-blur','KK Buram',['status'=>'pending','readable'=>false],1);
        $this->seedScenarioAction($s,'reject_blur','Tolak: Dokumen Buram','document','kk-blur',['status'=>'rejected','reason'=>'Dokumen buram; unggah ulang file yang terbaca.'],['readable'=>false],'warning',1);
        $this->seedScenarioAction($s,'verify_blur','Verifikasi Dokumen','document','kk-blur',['status'=>'verified'],[],'danger',2);
        $this->seedScenarioAssertion($s,'rejected','Dokumen ditolak',StateEqualsValidator::class,['entity_type'=>'document','entity_key'=>'kk-blur','path'=>'status','expected'=>'rejected'],60,true,1);
        $this->seedScenarioAssertion($s,'no-verify','Tidak memverifikasi dokumen buram',EventNotExistsValidator::class,['action_code'=>'verify_blur'],40,true,2);
    }

    private function paymentScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCAO-PAYMENT-01','name'=>'Tangani Nominal Pembayaran Tidak Cocok','description'=>'Menolak payment mismatch tanpa bypass.','instructions'=>'Bukti pembayaran ada tetapi nominal tidak sesuai tagihan. Tolak/minta perbaikan; jangan verify.','time_limit_minutes'=>8,'sort_order'=>4]);
        $this->seedScenarioRecord($s,'payment','fee-mismatch','Pembayaran Pendaftaran',['status'=>'pending','amount_matches'=>false],1);
        $this->seedScenarioAction($s,'reject_mismatch','Tolak Pembayaran','payment','fee-mismatch',['status'=>'rejected','reason'=>'Nominal tidak sesuai.'],['amount_matches'=>false],'warning',1);
        $this->seedScenarioAction($s,'verify_mismatch','Verifikasi Pembayaran','payment','fee-mismatch',['status'=>'verified'],[],'danger',2);
        $this->seedScenarioAssertion($s,'rejected','Pembayaran mismatch ditolak',StateEqualsValidator::class,['entity_type'=>'payment','entity_key'=>'fee-mismatch','path'=>'status','expected'=>'rejected'],60,true,1);
        $this->seedScenarioAssertion($s,'no-verify','Tidak melakukan verify mismatch',EventNotExistsValidator::class,['action_code'=>'verify_mismatch'],40,true,2);
    }

    private function testScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCAO-TEST-01','name'=>'Pilih Sesi Tes yang Tersedia','description'=>'Membantu pendaftar tanpa melakukan overbooking.','instructions'=>'Sesi pagi penuh. Sesi siang masih tersedia. Tempatkan pendaftar di sesi siang; jangan paksa sesi pagi.','time_limit_minutes'=>8,'sort_order'=>5]);
        $this->seedScenarioRecord($s,'test_booking','candidate-2','Booking Tes',['morning_full'=>true,'afternoon_available'=>true,'session'=>null],1);
        $this->seedScenarioAction($s,'choose_afternoon','Pilih Sesi Siang','test_booking','candidate-2',['session'=>'afternoon'],['afternoon_available'=>true],'success',1);
        $this->seedScenarioAction($s,'overbook_morning','Paksa Sesi Pagi','test_booking','candidate-2',['session'=>'morning-overbooked'],[],'danger',2);
        $this->seedScenarioAssertion($s,'afternoon','Memilih sesi tersedia',StateEqualsValidator::class,['entity_type'=>'test_booking','entity_key'=>'candidate-2','path'=>'session','expected'=>'afternoon'],60,true,1);
        $this->seedScenarioAssertion($s,'no-overbook','Tidak melakukan overbooking',EventNotExistsValidator::class,['action_code'=>'overbook_morning'],40,true,2);
    }

    private function escalationScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,['code'=>'SCAO-ESCALATE-01','name'=>'Eskalasi Konfigurasi yang Hilang','description'=>'Membedakan masalah konfigurasi dari tugas verifikasi operator.','instructions'=>'Requirement dokumen wajib tidak muncul pada registration karena konfigurasi unit bermasalah. Eskalasi ke Admin Unit; jangan bypass requirement.','time_limit_minutes'=>8,'sort_order'=>6]);
        $this->seedScenarioRecord($s,'issue','missing-requirement','Masalah Konfigurasi',['issue_type'=>'configuration_missing','operator_action'=>null,'workflow_bypassed'=>false],1);
        $this->seedScenarioAction($s,'escalate_admin_unit','Eskalasi ke Admin Unit','issue','missing-requirement',['operator_action'=>'escalated'],[],'success',1);
        $this->seedScenarioAction($s,'bypass_requirement','Bypass Requirement','issue','missing-requirement',['operator_action'=>'bypassed','workflow_bypassed'=>true],[],'danger',2);
        $this->seedScenarioAssertion($s,'escalated','Masalah dieskalasikan',StateEqualsValidator::class,['entity_type'=>'issue','entity_key'=>'missing-requirement','path'=>'operator_action','expected'=>'escalated'],60,true,1);
        $this->seedScenarioAssertion($s,'no-bypass','Tidak melakukan bypass workflow',EventNotExistsValidator::class,['action_code'=>'bypass_requirement'],40,true,2);
    }
    private function testScheduleSupportScenario(CertificationProgram $program): void
    {
        $s=$this->seedScenario($program,[
            'code'=>'SCAO-TEST-CONFIRM-01',
            'name'=>'Dampingi Peserta Menyelesaikan Jadwal Tes',
            'description'=>'Mengarahkan peserta menyelesaikan seluruh tes wajib dan konfirmasi tanpa mengambil alih happy path.',
            'instructions'=>'Peserta memiliki 3 tes wajib tetapi baru memilih 1 sesi. Bantu dengan mengarahkan peserta memilih 2 sesi yang tersisa lalu melakukan konfirmasi final. Jangan pilihkan secara paksa dan jangan cetak kartu sebelum konfirmasi.',
            'time_limit_minutes'=>8,
            'sort_order'=>7,
        ]);
        $this->seedScenarioRecord($s,'test_schedule','candidate-3','Jadwal Tes Kandidat',[
            'required_tests'=>3,
            'booked_tests'=>1,
            'confirmed'=>false,
            'support_action'=>null,
            'card_printed_early'=>false,
        ],1);
        $this->seedScenarioAction($s,'guide_complete','Arahkan Lengkapi dan Konfirmasi','test_schedule','candidate-3',[
            'booked_tests'=>3,
            'confirmed'=>true,
            'support_action'=>'guided_to_complete_and_confirm',
        ],[],'success',1);
        $this->seedScenarioAction($s,'operator_force_select','Pilihkan Paksa oleh Operator','test_schedule','candidate-3',[
            'booked_tests'=>3,
            'confirmed'=>true,
            'support_action'=>'operator_forced_selection',
        ],[],'danger',2);
        $this->seedScenarioAction($s,'print_early','Cetak Kartu Sebelum Konfirmasi','test_schedule','candidate-3',[
            'card_printed_early'=>true,
        ],[],'danger',3);
        $this->seedScenarioAssertion($s,'all-booked','Semua tes wajib dilengkapi',StateEqualsValidator::class,[
            'entity_type'=>'test_schedule','entity_key'=>'candidate-3','path'=>'booked_tests','expected'=>3,
        ],30,true,1);
        $this->seedScenarioAssertion($s,'confirmed','Jadwal dikonfirmasi',StateEqualsValidator::class,[
            'entity_type'=>'test_schedule','entity_key'=>'candidate-3','path'=>'confirmed','expected'=>true,
        ],30,true,2);
        $this->seedScenarioAssertion($s,'guided','TU hanya memandu happy path',StateEqualsValidator::class,[
            'entity_type'=>'test_schedule','entity_key'=>'candidate-3','path'=>'support_action','expected'=>'guided_to_complete_and_confirm',
        ],25,true,3);
        $this->seedScenarioAssertion($s,'no-early-card','Tidak mencetak kartu terlalu dini',EventNotExistsValidator::class,[
            'action_code'=>'print_early',
        ],15,true,4);
    }

}
