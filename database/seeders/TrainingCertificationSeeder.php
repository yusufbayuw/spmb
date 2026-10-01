<?php

namespace Database\Seeders;

use App\Models\CertificationProgram;
use App\Models\TrainingLesson;
use App\Models\TrainingModule;
use App\Models\TrainingProgram;
use Illuminate\Database\Seeder;

class TrainingCertificationSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'training' => [
                    'code' => 'TRN-ADMIN',
                    'name' => 'Pelatihan Administrator SPMB',
                    'description' => 'Pelatihan untuk Admin Pusat yang mengelola konfigurasi, akses, audit, dan tata kelola SPMB.',
                    'target_role' => 'super_admin',
                    'version' => '1.0',
                    'sort_order' => 1,
                ],
                'certification' => [
                    'code' => 'SCA',
                    'name' => 'SPMB Certified Administrator',
                    'description' => 'Sertifikasi kompetensi Administrator SPMB.',
                    'passing_score' => 85,
                ],
                'modules' => [
                    ['Arsitektur, Role, dan Batas Akses', 'Memahami panel, role, unit, serta prinsip least privilege.', 15],
                    ['Konfigurasi Global dan Unit', 'Mengelola identitas aplikasi, unit, konfigurasi penerimaan, dan perubahan versi.', 25],
                    ['Audit, Privasi, dan Pemulihan', 'Memahami audit trail, perlindungan data, backup, eskalasi insiden, dan pemulihan layanan.', 25],
                ],
            ],
            [
                'training' => [
                    'code' => 'TRN-UNIT',
                    'name' => 'Pelatihan Administrator Unit SPMB',
                    'description' => 'Pelatihan untuk Admin Unit yang menyiapkan dan mengendalikan proses penerimaan pada unitnya.',
                    'target_role' => 'admin_unit',
                    'version' => '1.0',
                    'sort_order' => 2,
                ],
                'certification' => [
                    'code' => 'SCUA',
                    'name' => 'SPMB Certified Unit Administrator',
                    'description' => 'Sertifikasi kompetensi Administrator Unit SPMB.',
                    'passing_score' => 80,
                ],
                'modules' => [
                    ['Konfigurasi Penerimaan Unit', 'Menyiapkan profil, pembukaan, jalur, persyaratan, workflow, dan publikasi konfigurasi.', 25],
                    ['Dokumen, Pembayaran, dan Tes', 'Memahami alur dokumen, Virtual Account, pembayaran, tes, serta dependensi antar tahap.', 25],
                    ['Seleksi, Publikasi, dan Daftar Ulang', 'Mengelola seleksi, publikasi hasil, penawaran, daftar ulang, dan penyelesaian proses.', 25],
                ],
            ],
            [
                'training' => [
                    'code' => 'TRN-TU',
                    'name' => 'Pelatihan Operator SPMB',
                    'description' => 'Pelatihan operasional untuk TU/operator yang memproses data pendaftar sehari-hari.',
                    'target_role' => 'tu',
                    'version' => '1.0',
                    'sort_order' => 3,
                ],
                'certification' => [
                    'code' => 'SCAO',
                    'name' => 'SPMB Certified Admission Operator',
                    'description' => 'Sertifikasi kompetensi Operator Penerimaan SPMB.',
                    'passing_score' => 80,
                ],
                'modules' => [
                    ['Validasi Data Pendaftar', 'Memeriksa data identitas, status proses, dan menangani ketidaksesuaian secara aman.', 20],
                    ['Verifikasi Dokumen dan Pembayaran', 'Memeriksa dokumen serta pembayaran tanpa melewati kontrol keamanan dan audit.', 25],
                    ['Tes, Status, dan Eskalasi', 'Menangani sesi tes, hasil, perubahan status, dan eskalasi kasus kepada Admin Unit.', 20],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $training = TrainingProgram::query()->firstOrCreate(
                ['code' => $definition['training']['code']],
                $definition['training'] + ['is_active' => true],
            );

            foreach ($definition['modules'] as $index => [$title, $content, $duration]) {
                $module = TrainingModule::query()->firstOrCreate(
                    ['training_program_id' => $training->id, 'title' => $title],
                    [
                        'description' => $content,
                        'sort_order' => $index + 1,
                        'is_required' => true,
                    ],
                );

                TrainingLesson::query()->firstOrCreate(
                    ['training_module_id' => $module->id, 'title' => $title],
                    [
                        'type' => 'guide',
                        'content' => '<p>'.$content.'</p>',
                        'duration_minutes' => $duration,
                        'sort_order' => 1,
                        'is_required' => true,
                    ],
                );
            }

            CertificationProgram::query()->firstOrCreate(
                ['code' => $definition['certification']['code']],
                [
                    'name' => $definition['certification']['name'],
                    'description' => $definition['certification']['description'],
                    'target_role' => $training->target_role,
                    'version' => $training->version,
                    'passing_score' => $definition['certification']['passing_score'],
                    'valid_months' => 24,
                    'training_program_id' => $training->id,
                    'is_active' => true,
                    'sort_order' => $training->sort_order,
                ],
            );
        }
    }
}
