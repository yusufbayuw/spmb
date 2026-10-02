<?php

namespace Database\Seeders;

use App\Models\CertificationProgram;
use App\Models\PracticalAssertion;
use App\Models\PracticalScenario;
use App\Models\PracticalScenarioAction;
use App\Models\PracticalScenarioRecord;
use App\Models\TrainingLesson;
use App\Models\TrainingModule;
use App\Models\TrainingProgram;
use App\Services\PracticalValidators\EventNotExistsValidator;
use App\Services\PracticalValidators\StateEqualsValidator;
use App\Services\PracticalValidators\StateUnchangedValidator;
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

            CertificationProgram::query()->updateOrCreate(
                ['code' => $definition['certification']['code']],
                [
                    'name' => $definition['certification']['name'],
                    'description' => $definition['certification']['description'],
                    'target_role' => $training->target_role,
                    'version' => $training->version,
                    'passing_score' => $definition['certification']['passing_score'],
                    'theory_weight' => 40,
                    'practical_weight' => 60,
                    'practical_passing_score' => 80,
                    'valid_months' => 24,
                    'training_program_id' => $training->id,
                    'is_active' => true,
                    'sort_order' => $training->sort_order,
                ],
            );
        }

        $this->seedAdministratorScenario();
        $this->seedUnitAdministratorScenario();
        $this->seedOperatorScenario();
    }

    private function seedAdministratorScenario(): void
    {
        $program = CertificationProgram::query()->where('code', 'SCA')->firstOrFail();

        $scenario = PracticalScenario::query()->updateOrCreate(
            ['code' => 'SCA-ACCESS-01'],
            [
                'certification_program_id' => $program->id,
                'name' => 'Perbaiki Akses Tanpa Privilege Berlebihan',
                'description' => 'Memastikan Admin Pusat memperbaiki assignment staff dengan prinsip least privilege.',
                'instructions' => 'Seorang TU tidak dapat mengakses unitnya karena assignment unit belum benar. Perbaiki akses tanpa menaikkan role menjadi Super Admin dan tanpa menonaktifkan unit.',
                'time_limit_minutes' => 15,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $this->syncRecord($scenario, 'staff', 'operator-a', 'Operator TU', [
            'role' => 'tu',
            'unit_assigned' => false,
            'can_access_unit' => false,
        ], 1);
        $this->syncRecord($scenario, 'unit', 'unit-a', 'Unit A', [
            'status' => 'active',
        ], 2);

        $this->syncAction($scenario, 'assign_unit', 'Tetapkan Unit yang Benar', 'staff', 'operator-a', [
            'unit_assigned' => true,
            'can_access_unit' => true,
        ], [], 'success', 1);
        $this->syncAction($scenario, 'grant_super_admin', 'Naikkan Menjadi Super Admin', 'staff', 'operator-a', [
            'role' => 'super_admin',
            'can_access_unit' => true,
        ], [], 'danger', 2);
        $this->syncAction($scenario, 'deactivate_unit', 'Nonaktifkan Unit', 'unit', 'unit-a', [
            'status' => 'inactive',
        ], [], 'danger', 3);

        $this->syncAssertion($scenario, 'access-restored', 'Akses unit dipulihkan', StateEqualsValidator::class, [
            'entity_type' => 'staff', 'entity_key' => 'operator-a', 'path' => 'can_access_unit', 'expected' => true,
        ], 30, true, 1);
        $this->syncAssertion($scenario, 'role-preserved', 'Role TU tetap dipertahankan', StateEqualsValidator::class, [
            'entity_type' => 'staff', 'entity_key' => 'operator-a', 'path' => 'role', 'expected' => 'tu',
        ], 30, true, 2);
        $this->syncAssertion($scenario, 'unit-active', 'Unit tetap aktif', StateEqualsValidator::class, [
            'entity_type' => 'unit', 'entity_key' => 'unit-a', 'path' => 'status', 'expected' => 'active',
        ], 20, true, 3);
        $this->syncAssertion($scenario, 'no-super-admin', 'Tidak memberikan privilege Super Admin', EventNotExistsValidator::class, [
            'action_code' => 'grant_super_admin',
        ], 20, true, 4);
    }

    private function seedUnitAdministratorScenario(): void
    {
        $program = CertificationProgram::query()->where('code', 'SCUA')->firstOrFail();

        $scenario = PracticalScenario::query()->updateOrCreate(
            ['code' => 'SCUA-OPENING-01'],
            [
                'certification_program_id' => $program->id,
                'name' => 'Pause Pendaftaran Tanpa Merusak Data',
                'description' => 'Mengukur kemampuan Admin Unit menghentikan sementara pendaftaran secara aman.',
                'instructions' => 'Pendaftaran Gelombang 1 sedang terbuka dan sudah memiliki 12 pendaftar. Hentikan sementara penerimaan sehingga tidak terlihat oleh pendaftar, tetapi jangan menutup permanen dan jangan mengubah data pendaftar yang sudah ada.',
                'time_limit_minutes' => 10,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $this->syncRecord($scenario, 'opening', 'gelombang-1', 'Gelombang 1 · 2027/2028', [
            'status' => 'open',
            'visible_to_applicants' => true,
            'registration_count' => 12,
        ], 1);

        $this->syncAction($scenario, 'pause_opening', 'Pause Pendaftaran', 'opening', 'gelombang-1', [
            'status' => 'paused',
            'visible_to_applicants' => false,
        ], ['status' => 'open'], 'warning', 1);
        $this->syncAction($scenario, 'close_opening', 'Tutup Pendaftaran', 'opening', 'gelombang-1', [
            'status' => 'closed',
            'visible_to_applicants' => false,
        ], ['status' => 'open'], 'danger', 2);
        $this->syncAction($scenario, 'delete_registrations', 'Hapus Data Pendaftar', 'opening', 'gelombang-1', [
            'registration_count' => 0,
        ], [], 'danger', 3);

        $this->syncAssertion($scenario, 'paused', 'Status menjadi paused', StateEqualsValidator::class, [
            'entity_type' => 'opening', 'entity_key' => 'gelombang-1', 'path' => 'status', 'expected' => 'paused',
        ], 35, true, 1);
        $this->syncAssertion($scenario, 'hidden', 'Tidak terlihat oleh pendaftar', StateEqualsValidator::class, [
            'entity_type' => 'opening', 'entity_key' => 'gelombang-1', 'path' => 'visible_to_applicants', 'expected' => false,
        ], 25, true, 2);
        $this->syncAssertion($scenario, 'data-preserved', 'Jumlah pendaftar tetap', StateUnchangedValidator::class, [
            'entity_type' => 'opening', 'entity_key' => 'gelombang-1', 'path' => 'registration_count',
        ], 25, true, 3);
        $this->syncAssertion($scenario, 'no-delete', 'Tidak melakukan penghapusan data', EventNotExistsValidator::class, [
            'action_code' => 'delete_registrations',
        ], 15, true, 4);
    }

    private function seedOperatorScenario(): void
    {
        $program = CertificationProgram::query()->where('code', 'SCAO')->firstOrFail();

        $scenario = PracticalScenario::query()->updateOrCreate(
            ['code' => 'SCAO-VERIFY-01'],
            [
                'certification_program_id' => $program->id,
                'name' => 'Verifikasi Berkas dan Pembayaran',
                'description' => 'Mengukur ketelitian TU saat memverifikasi data operasional pendaftar.',
                'instructions' => 'Rapor dinyatakan valid, KK tidak valid, dan pembayaran sesuai nominal. Tetapkan status masing-masing item secara tepat.',
                'time_limit_minutes' => 12,
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $this->syncRecord($scenario, 'document', 'rapor', 'Rapor', ['status' => 'pending', 'valid' => true], 1);
        $this->syncRecord($scenario, 'document', 'kk', 'Kartu Keluarga', ['status' => 'pending', 'valid' => false], 2);
        $this->syncRecord($scenario, 'payment', 'registration-fee', 'Pembayaran Pendaftaran', ['status' => 'pending', 'amount_matches' => true], 3);

        $this->syncAction($scenario, 'verify_rapor', 'Verifikasi Rapor', 'document', 'rapor', ['status' => 'verified'], ['status' => 'pending'], 'success', 1);
        $this->syncAction($scenario, 'reject_rapor', 'Tolak Rapor', 'document', 'rapor', ['status' => 'rejected'], ['status' => 'pending'], 'danger', 2);
        $this->syncAction($scenario, 'verify_kk', 'Verifikasi KK', 'document', 'kk', ['status' => 'verified'], ['status' => 'pending'], 'success', 3);
        $this->syncAction($scenario, 'reject_kk', 'Tolak KK', 'document', 'kk', ['status' => 'rejected'], ['status' => 'pending'], 'danger', 4);
        $this->syncAction($scenario, 'verify_payment', 'Verifikasi Pembayaran', 'payment', 'registration-fee', ['status' => 'verified'], ['status' => 'pending'], 'success', 5);
        $this->syncAction($scenario, 'reject_payment', 'Tolak Pembayaran', 'payment', 'registration-fee', ['status' => 'rejected'], ['status' => 'pending'], 'danger', 6);

        $this->syncAssertion($scenario, 'rapor-ok', 'Rapor diverifikasi', StateEqualsValidator::class, [
            'entity_type' => 'document', 'entity_key' => 'rapor', 'path' => 'status', 'expected' => 'verified',
        ], 30, true, 1);
        $this->syncAssertion($scenario, 'kk-rejected', 'KK yang tidak valid ditolak', StateEqualsValidator::class, [
            'entity_type' => 'document', 'entity_key' => 'kk', 'path' => 'status', 'expected' => 'rejected',
        ], 40, true, 2);
        $this->syncAssertion($scenario, 'payment-ok', 'Pembayaran diverifikasi', StateEqualsValidator::class, [
            'entity_type' => 'payment', 'entity_key' => 'registration-fee', 'path' => 'status', 'expected' => 'verified',
        ], 30, true, 3);
    }

    private function syncRecord(PracticalScenario $scenario, string $type, string $key, string $label, array $state, int $sort): void
    {
        PracticalScenarioRecord::query()->updateOrCreate(
            ['practical_scenario_id' => $scenario->id, 'entity_type' => $type, 'entity_key' => $key],
            ['label' => $label, 'initial_state' => $state, 'sort_order' => $sort],
        );
    }

    private function syncAction(
        PracticalScenario $scenario,
        string $code,
        string $label,
        string $targetType,
        string $targetKey,
        array $mutation,
        array $allowedWhen,
        string $color,
        int $sort,
    ): void {
        PracticalScenarioAction::query()->updateOrCreate(
            ['practical_scenario_id' => $scenario->id, 'code' => $code],
            [
                'label' => $label,
                'target_type' => $targetType,
                'target_key' => $targetKey,
                'mutation' => $mutation,
                'allowed_when' => $allowedWhen,
                'button_color' => $color,
                'requires_confirmation' => $color === 'danger',
                'sort_order' => $sort,
                'is_active' => true,
            ],
        );
    }

    private function syncAssertion(
        PracticalScenario $scenario,
        string $code,
        string $name,
        string $validator,
        array $config,
        float $points,
        bool $critical,
        int $sort,
    ): void {
        PracticalAssertion::query()->updateOrCreate(
            ['practical_scenario_id' => $scenario->id, 'code' => $code],
            [
                'name' => $name,
                'validator_class' => $validator,
                'config' => $config,
                'points' => $points,
                'is_critical' => $critical,
                'is_active' => true,
                'sort_order' => $sort,
            ],
        );
    }
}
