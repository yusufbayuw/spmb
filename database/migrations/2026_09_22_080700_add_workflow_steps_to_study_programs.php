<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('study_programs', 'workflow_steps')) {
            Schema::table('study_programs', function (Blueprint $table): void {
                $table->json('workflow_steps')->nullable()->after('is_active');
            });
        }

        $defaultWorkflow = [
            ['stage' => 'data_validation', 'label' => 'Validasi Data', 'description' => 'Pemeriksaan data identitas calon mahasiswa.', 'visible' => true],
            ['stage' => 'virtual_account', 'label' => 'Penerbitan Virtual Account', 'description' => 'Penerbitan nomor Virtual Account untuk pembayaran formulir.', 'visible' => true],
            ['stage' => 'payment', 'label' => 'Pembayaran Formulir', 'description' => 'Pembayaran biaya formulir pendaftaran.', 'visible' => true],
            ['stage' => 'payment_verification', 'label' => 'Verifikasi Pembayaran Formulir', 'description' => 'Pemeriksaan pembayaran formulir oleh petugas.', 'visible' => true],
            ['stage' => 'applicant_card', 'label' => 'Kartu Pendaftar', 'description' => 'Kartu pendaftar diterbitkan setelah persyaratan awal terpenuhi.', 'visible' => true],
            ['stage' => 'documents', 'label' => 'Melengkapi Berkas', 'description' => 'Calon mahasiswa melengkapi dokumen sesuai ketentuan program studi.', 'visible' => true],
            ['stage' => 'document_verification', 'label' => 'Verifikasi Berkas', 'description' => 'Petugas memeriksa kelengkapan dan validitas berkas.', 'visible' => true],
            ['stage' => 'tests', 'label' => 'Rangkaian Tes', 'description' => 'Calon mahasiswa mengikuti tes yang diwajibkan.', 'visible' => true],
            ['stage' => 'selection', 'label' => 'Seleksi Calon Mahasiswa', 'description' => 'Hasil tes dan persyaratan diproses dalam tahap seleksi.', 'visible' => true],
            ['stage' => 'announcement', 'label' => 'Pengumuman', 'description' => 'Hasil penerimaan diumumkan kepada calon mahasiswa.', 'visible' => true],
            ['stage' => 'waiting_list', 'label' => 'Daftar Tunggu', 'description' => 'Tahap ini hanya tampil untuk calon mahasiswa yang berada pada daftar tunggu.', 'visible' => true],
            ['stage' => 'admission_offer', 'label' => 'Pembayaran Registrasi', 'description' => 'Konfirmasi penerimaan dan kewajiban registrasi sesuai kebijakan program studi.', 'visible' => true],
            ['stage' => 're_registration', 'label' => 'Daftar Ulang', 'description' => 'Pemenuhan persyaratan daftar ulang yang ditetapkan perguruan tinggi.', 'visible' => true],
            ['stage' => 'enrollment', 'label' => 'Perwalian', 'description' => 'Tahap administrasi awal mahasiswa sebelum proses akademik dimulai.', 'visible' => true],
            ['stage' => 'completed', 'label' => 'Selesai', 'description' => 'Seluruh rangkaian penerimaan pada program studi telah selesai.', 'visible' => true],
        ];

        DB::table('study_programs')
            ->whereNull('workflow_steps')
            ->update([
                'workflow_steps' => json_encode($defaultWorkflow, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('study_programs', 'workflow_steps')) {
            Schema::table('study_programs', function (Blueprint $table): void {
                $table->dropColumn('workflow_steps');
            });
        }
    }
};
