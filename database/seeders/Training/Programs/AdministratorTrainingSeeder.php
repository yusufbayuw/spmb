<?php

namespace Database\Seeders\Training\Programs;

use Database\Seeders\Training\CurriculumSeeder;

class AdministratorTrainingSeeder extends CurriculumSeeder
{
    public function run(): void
    {
        $program = $this->ensureTrainingProgram([
            'code' => 'TRN-ADMIN',
            'name' => 'Pelatihan Administrator SPMB',
            'description' => 'Kurikulum kompetensi Admin Pusat untuk arsitektur, akses, konfigurasi global, tata kelola, keamanan, audit, dan kontinuitas layanan SPMB.',
            'target_role' => 'super_admin',
            'version' => '1.0',
            'sort_order' => 1,
        ]);

        $this->ensureCertificationProgram($program, [
            'code' => 'SCA',
            'name' => 'SPMB Certified Administrator',
            'description' => 'Sertifikasi kompetensi Administrator SPMB untuk pengelolaan sistem lintas unit.',
            'passing_score' => 85,
            'practical_passing_score' => 80,
            'theory_weight' => 40,
            'practical_weight' => 60,
            'valid_months' => 24,
            'sort_order' => 1,
        ]);

        $this->seedModule($program, 'sca-m01', 'Arsitektur, Role, dan Batas Akses', 'Memahami struktur SPMB, panel, domain, role, serta prinsip least privilege.', 1, [
            $this->lesson('Arsitektur, Role, dan Batas Akses', 'Menjelaskan batas antara panel staff, panel pendaftar, domain operasional, dan otorisasi role.', [
                'Panel admin dipakai Super Admin, Admin Unit, dan TU; panel pendaftar dipakai calon peserta.',
                'Role menentukan ruang kerja; unit membatasi data operasional staff unit.',
                'Least privilege berarti memberi hak minimum yang diperlukan untuk tugas.',
            ], [
                'Identifikasi panel yang digunakan oleh setiap role.',
                'Bedakan akses global Super Admin dengan akses scoped Admin Unit/TU.',
                'Sebelum mengubah role, cari penyebab akses: status user, unit assignment, status unit, lalu permission.',
            ], [
                'Memberi Super Admin hanya agar masalah akses cepat selesai.',
                'Menganggap semua error akses adalah masalah password.',
                'Menggunakan akun bersama untuk beberapa petugas.',
            ], 'Masalah akses harus diselesaikan pada lapisan yang benar tanpa memperluas privilege.', 25, 'guide', 'overview'),
            $this->lesson('Mode Operasional K12, Higher Education, dan Mixed', 'Memahami perbedaan mode operasional dan dampaknya terhadap konfigurasi.', [
                'K12 berorientasi unit sekolah dan jalur penerimaan.',
                'Higher Education dapat menggunakan program studi dan konfigurasi per program.',
                'Mixed mendukung kebutuhan keduanya dalam satu deployment.',
            ], [
                'Periksa mode deployment sebelum membuat konfigurasi.',
                'Pastikan resource yang digunakan sesuai mode.',
                'Uji tampilan pendaftar setelah perubahan mode atau konfigurasi.',
            ], [
                'Menganggap fitur program studi selalu relevan pada K12.',
                'Mengubah mode produksi tanpa mengecek data/configuration existing.',
            ], 'Mode operasional menentukan fitur yang relevan, tetapi prinsip keamanan dan workflow tetap sama.', 20, 'content', 'modes'),
            $this->lesson('Lifecycle Data dan Domain Utama', 'Memahami hubungan unit, opening, registration, document, payment, test, selection, dan enrollment.', [
                'Registration bergerak melalui state/workflow, bukan kumpulan halaman yang berdiri sendiri.',
                'Perubahan konfigurasi dapat memengaruhi pendaftar baru tanpa boleh merusak data existing.',
                'Lifecycle active, withdrawn, cancelled, archived berbeda dari hard delete.',
            ], [
                'Telusuri hubungan dari Unit ke Registration Opening dan Registration.',
                'Identifikasi data transaksional yang harus dipertahankan.',
                'Gunakan lifecycle untuk menonaktifkan data operasional.',
            ], [
                'Hard delete untuk merapikan data produksi.',
                'Mengubah relasi atau konfigurasi tanpa memikirkan registration existing.',
            ], 'Administrator harus melihat SPMB sebagai sistem stateful dengan jejak audit dan dependensi antar-domain.', 25, 'content', 'domain'),
        ]);

        $this->seedModule($program, 'sca-m02', 'Manajemen User, Role, dan Unit', 'Mengelola akun staff, assignment unit, status aktif, dan batas kewenangan.', 2, [
            $this->lesson('Membuat dan Menempatkan Staff', 'Menempatkan staff ke role dan unit yang tepat sejak awal.', [
                'Super Admin bersifat global.',
                'Admin Unit dan TU harus memiliki unit operasional yang valid.',
                'Status user inactive harus mencegah akses panel.',
            ], [
                'Tentukan tugas petugas.',
                'Pilih role minimum yang memenuhi tugas.',
                'Tetapkan unit yang benar.',
                'Uji login dan scope data dengan akun tersebut.',
            ], [
                'Membuat semua staff sebagai Admin Unit.',
                'Meninggalkan staff lama tetap aktif setelah mutasi.',
            ], 'Assignment role dan unit adalah kontrol keamanan utama, bukan sekadar pengaturan menu.', 20, 'guide', 'staff'),
            $this->lesson('Menangani Perubahan Tugas dan Offboarding', 'Melakukan perubahan akses tanpa kehilangan jejak audit.', [
                'Akun personal harus dipertahankan sebagai identitas audit.',
                'Offboarding lebih aman dengan menonaktifkan akun daripada menghapus histori.',
                'Perubahan unit harus mengikuti perubahan tanggung jawab aktual.',
            ], [
                'Konfirmasi tanggal perubahan tanggung jawab.',
                'Ubah role/unit sesuai kebutuhan baru.',
                'Nonaktifkan akses jika user tidak lagi bertugas.',
                'Review data atau proses yang masih menunggu user lama.',
            ], [
                'Menghapus user yang memiliki histori verifikasi.',
                'Memindahkan unit tanpa review tanggung jawab.',
            ], 'Akses mengikuti jabatan/tugas; histori tetap dipertahankan.', 20, 'guide', 'offboarding'),
            $this->lesson('Review Akses Berkala', 'Melakukan pemeriksaan berkala atas akses staff.', [
                'User aktif harus relevan dengan organisasi saat ini.',
                'Role berprivilege tinggi perlu ditinjau lebih ketat.',
                'Unit inactive tidak boleh menjadi jalur akses staff unit.',
            ], [
                'Ekspor/lihat daftar user staff.',
                'Cocokkan role dan unit dengan struktur organisasi.',
                'Nonaktifkan akses yang tidak lagi diperlukan.',
                'Catat hasil review.',
            ], [
                'Review hanya dilakukan setelah insiden.',
                'Menganggap akun yang jarang dipakai otomatis aman.',
            ], 'Access review berkala mengurangi akumulasi privilege yang tidak diperlukan.', 20, 'content', 'access-review'),
        ]);

        $this->seedModule($program, 'sca-m03', 'Konfigurasi Global dan Unit', 'Mengelola identitas aplikasi, unit, mode operasional, dan konfigurasi lintas deployment.', 3, [
            $this->lesson('Konfigurasi Global dan Unit', 'Membedakan konfigurasi global dengan konfigurasi yang dimiliki masing-masing unit.', [
                'Branding dan konfigurasi aplikasi global berlaku lintas unit.',
                'Konfigurasi penerimaan unit harus tetap scoped.',
                'Perubahan global perlu diperlakukan sebagai change management.',
            ], [
                'Identifikasi dampak perubahan: global atau unit.',
                'Lakukan perubahan di area yang tepat.',
                'Verifikasi hasil pada panel admin dan pendaftar.',
            ], [
                'Mengubah .env untuk hal yang sudah tersedia di panel pusat.',
                'Menganggap perubahan global hanya berdampak pada satu unit.',
            ], 'Tempatkan konfigurasi pada scope yang tepat agar white-label dan multi-unit tetap terkontrol.', 25, 'guide', 'overview'),
            $this->lesson('White-label dan Identitas Portal', 'Mengelola nama portal, logo, dan identitas tanpa akses server rutin.', [
                'White-label seharusnya dikelola dari konfigurasi terpusat.',
                'Aset branding harus diuji pada desktop dan mobile.',
                'Perubahan identitas tidak boleh memengaruhi data penerimaan.',
            ], [
                'Siapkan aset dengan format yang didukung.',
                'Ubah konfigurasi branding.',
                'Periksa login, homepage, panel, dan dokumen cetak terkait.',
            ], [
                'Mengunggah aset terlalu besar tanpa optimasi.',
                'Mengubah domain/URL tanpa koordinasi konfigurasi infrastruktur.',
            ], 'Branding adalah konfigurasi presentasi; perubahan infrastruktur tetap memerlukan prosedur deployment.', 20, 'guide', 'branding'),
            $this->lesson('Unit Aktif, Inaktif, dan Dampak Operasional', 'Mengendalikan visibilitas unit tanpa menghapus data historis.', [
                'Unit inactive harus hilang dari alur operasional/public yang relevan.',
                'Data historis tetap diperlukan untuk audit dan laporan.',
                'Reaktivasi harus dilakukan dengan review konfigurasi.',
            ], [
                'Pastikan tidak ada proses kritis yang sedang berjalan.',
                'Nonaktifkan unit melalui kontrol resmi.',
                'Verifikasi public surface dan panel staff.',
                'Saat reaktivasi, review opening/configuration sebelum dipublikasikan.',
            ], [
                'Menghapus unit.',
                'Menonaktifkan unit saat proses penerimaan aktif tanpa komunikasi.',
            ], 'On/off unit adalah lifecycle control, bukan penghapusan data.', 20, 'content', 'unit-lifecycle'),
        ]);

        $this->seedModule($program, 'sca-m04', 'Change, Release, dan Configuration Governance', 'Mengelola perubahan aplikasi dan konfigurasi secara terkontrol.', 4, [
            $this->lesson('Change Management untuk SPMB', 'Menentukan perubahan mana yang perlu review, testing, dan jadwal rilis.', [
                'Perubahan workflow, payment, selection, dan access control berisiko tinggi.',
                'Perubahan tampilan ringan tetap perlu regression check.',
                'Perubahan produksi harus memiliki rollback path.',
            ], [
                'Definisikan tujuan perubahan.',
                'Identifikasi area terdampak.',
                'Uji di non-production atau test suite.',
                'Rilis terkontrol dan monitor hasil.',
            ], [
                'Mengedit production langsung tanpa catatan.',
                'Menyamakan perubahan konfigurasi dengan perubahan source code.',
            ], 'Semakin dekat perubahan ke keputusan penerimaan dan data pribadi, semakin tinggi kebutuhan kontrol.', 25, 'content', 'change'),
            $this->lesson('Export/Import Konfigurasi', 'Memindahkan konfigurasi antar lingkungan/unit tanpa membawa data transaksional.', [
                'Konfigurasi dapat diperlakukan berbeda dari data pendaftar.',
                'Import harus divalidasi terhadap unit/mode target.',
                'Rahasia atau credential tidak boleh ikut dalam paket konfigurasi biasa.',
            ], [
                'Ekspor konfigurasi dari sumber.',
                'Review isi dan versi.',
                'Import ke target.',
                'Verifikasi hasil sebelum membuka penerimaan.',
            ], [
                'Mencampur data pendaftar dengan paket konfigurasi.',
                'Import tanpa backup atau review target.',
            ], 'Configuration portability harus tetap menjaga scope, versi, dan keamanan secret.', 20, 'guide', 'config-transfer'),
            $this->lesson('Rollback dan Pemulihan Perubahan', 'Menentukan cara kembali ke kondisi aman ketika perubahan bermasalah.', [
                'Rollback aplikasi berbeda dari rollback data.',
                'Migration irreversible memerlukan perhatian khusus.',
                'Backup yang tidak pernah diuji restore belum cukup.',
            ], [
                'Hentikan perubahan lanjutan.',
                'Identifikasi apakah masalah source, config, atau data.',
                'Gunakan rollback yang sesuai.',
                'Verifikasi layanan dan dokumentasikan insiden.',
            ], [
                'Restore database penuh untuk masalah konfigurasi kecil.',
                'Rollback code tanpa memperhatikan migration.',
            ], 'Rollback harus proporsional terhadap sumber masalah dan menjaga integritas data.', 25, 'guide', 'rollback'),
        ]);

        $this->seedModule($program, 'sca-m05', 'Workflow dan Integritas Proses Penerimaan', 'Memahami kontrol workflow sehingga administrator tidak menciptakan jalur bypass.', 5, [
            $this->lesson('State Machine Pendaftaran', 'Menjelaskan mengapa perpindahan stage harus melalui business rule.', [
                'Stage pendaftaran mewakili kesiapan proses.',
                'Transition harus menjaga prasyarat.',
                'Locking/transaksi digunakan pada operasi yang rawan concurrency.',
            ], [
                'Identifikasi current stage.',
                'Periksa prasyarat dan konfigurasi workflow.',
                'Gunakan aksi resmi untuk transition.',
                'Review audit trail jika state tidak sesuai.',
            ], [
                'Mengedit current_stage langsung.',
                'Membuat shortcut yang melewati verifikasi penting.',
            ], 'Workflow menjaga konsistensi proses dan jejak keputusan.', 25, 'content', 'state-machine'),
            $this->lesson('Mode Seleksi dan Publikasi', 'Memahami pemisahan keputusan internal dengan publikasi hasil.', [
                'Penetapan hasil dan publikasi merupakan langkah berbeda.',
                'Draft/review mengurangi risiko pengumuman prematur.',
                'Batch/flexible/manual harus sesuai kebijakan unit.',
            ], [
                'Pastikan data seleksi lengkap.',
                'Review keputusan internal.',
                'Publikasikan melalui mekanisme resmi.',
                'Verifikasi tampilan pendaftar.',
            ], [
                'Menganggap menyimpan hasil otomatis mempublikasikannya.',
                'Mengubah hasil setelah publikasi tanpa prosedur koreksi.',
            ], 'Keputusan dan publikasi harus dipisahkan agar kontrol kualitas tetap ada.', 20, 'guide', 'selection'),
            $this->lesson('Exception Handling', 'Menangani kasus yang tidak mengikuti jalur normal tanpa merusak governance.', [
                'Exception harus punya alasan, aktor, dan jejak audit.',
                'Tidak semua exception layak dibuat menjadi fitur permanen.',
                'Kasus data harus dibedakan dari kasus kebijakan.',
            ], [
                'Klasifikasikan exception.',
                'Cari prosedur resmi atau eskalasi.',
                'Catat keputusan dan dampak.',
                'Evaluasi apakah rule/config perlu diperbaiki.',
            ], [
                'Mengubah database manual untuk kasus tunggal.',
                'Membuat bypass permanen karena satu kejadian.',
            ], 'Exception harus dikompresi menjadi proses aman, bukan shortcut tidak terlacak.', 20, 'content', 'exceptions'),
        ]);

        $this->seedModule($program, 'sca-m06', 'Pelaporan, Ekspor, dan Data Governance', 'Mengendalikan akses terhadap data agregat maupun data individu.', 6, [
            $this->lesson('Scope Laporan dan Ekspor', 'Menjaga agar user hanya melihat data sesuai kewenangan.', [
                'Laporan unit harus scoped ke unit.',
                'Export dapat memperbesar risiko karena data keluar dari aplikasi.',
                'Hak melihat layar belum tentu sama dengan hak bulk export.',
            ], [
                'Tentukan tujuan ekspor.',
                'Pastikan scope user benar.',
                'Minimalkan kolom yang dibutuhkan.',
                'Simpan file hasil secara aman dan hapus ketika tidak diperlukan.',
            ], [
                'Mengirim spreadsheet penuh melalui kanal pribadi.',
                'Menggunakan export global untuk kebutuhan satu unit.',
            ], 'Bulk export adalah operasi sensitif dan harus mengikuti prinsip data minimization.', 20, 'guide', 'exports'),
            $this->lesson('Retensi dan Lifecycle Data', 'Menentukan kapan data tetap aktif, diarsipkan, atau diproses sesuai kebijakan.', [
                'Operational view sebaiknya fokus data aktif.',
                'Archived tidak berarti data boleh dipublikasikan kembali.',
                'Retensi harus terkait tujuan dan kewajiban organisasi.',
            ], [
                'Pisahkan kebutuhan operasional dari arsip.',
                'Gunakan lifecycle status.',
                'Review kebijakan retensi secara berkala.',
            ], [
                'Hard delete massal untuk membersihkan dashboard.',
                'Menyimpan export lokal tanpa batas waktu.',
            ], 'Lifecycle dan retensi harus menjaga operasional sekaligus kewajiban perlindungan data.', 20, 'content', 'retention'),
            $this->lesson('Data Minimization dan Need-to-Know', 'Mengurangi paparan data pribadi yang tidak diperlukan.', [
                'NIK, data orang tua, dokumen, dan hasil seleksi adalah data sensitif secara operasional.',
                'Tampilan dan export sebaiknya hanya memuat data yang diperlukan.',
                'Akses berdasarkan rasa ingin tahu bukan kebutuhan kerja.',
            ], [
                'Tentukan data yang benar-benar diperlukan untuk tugas.',
                'Gunakan filter/scope yang tersedia.',
                'Hindari salinan lokal tambahan.',
            ], [
                'Membuka data pendaftar di luar kebutuhan tugas.',
                'Menggunakan akun staff untuk membantu pihak lain tanpa otorisasi.',
            ], 'Need-to-know memperkecil dampak jika terjadi kesalahan atau kebocoran.', 20, 'content', 'minimization'),
        ]);

        $this->seedModule($program, 'sca-m07', 'Audit, Privasi, dan Pemulihan', 'Menggunakan audit trail, kontrol privasi, dan prosedur incident response.', 7, [
            $this->lesson('Audit, Privasi, dan Pemulihan', 'Menghubungkan audit trail, consent, akses, dan pemulihan dalam tata kelola SPMB.', [
                'Audit log harus immutable pada jalur aplikasi.',
                'Consent menyimpan snapshot agar bukti tidak berubah mengikuti teks terbaru.',
                'Insiden perlu containment, evidence, recovery, dan follow-up.',
            ], [
                'Identifikasi event, user, waktu, dan object terdampak.',
                'Batasi dampak tanpa menghapus evidence.',
                'Pulihkan layanan melalui prosedur aman.',
                'Dokumentasikan tindakan korektif.',
            ], [
                'Menghapus log untuk merapikan data.',
                'Mengubah bukti consent historis.',
                'Melakukan recovery tanpa verifikasi integritas.',
            ], 'Audit dan recovery harus mempertahankan bukti serta meminimalkan perubahan lanjutan.', 30, 'guide', 'overview'),
            $this->lesson('Membaca Audit Trail', 'Menggunakan audit log untuk menelusuri perubahan operasional.', [
                'Audit event perlu dibaca bersama actor, subject, old/new value, IP, dan metadata.',
                'Korelasi beberapa event dapat menjelaskan rangkaian perubahan.',
                'Audit bukan pengganti monitoring, tetapi bukti tindakan.',
            ], [
                'Mulai dari object atau waktu kejadian.',
                'Cari event terkait.',
                'Bandingkan state sebelum dan sesudah.',
                'Konfirmasi dengan user/prosedur bila diperlukan.',
            ], [
                'Menyimpulkan motif hanya dari satu event.',
                'Menganggap tidak ada log berarti tidak ada kejadian.',
            ], 'Gunakan audit trail sebagai evidence teknis dan gabungkan dengan konteks operasional.', 20, 'guide', 'audit'),
            $this->lesson('Incident Response untuk Data Pendaftar', 'Merespons insiden keamanan atau privasi secara terstruktur.', [
                'Prioritas awal adalah containment dan menjaga evidence.',
                'Scope insiden harus ditentukan: data apa, unit mana, periode kapan.',
                'Credential, session, atau account terdampak mungkin perlu dicabut.',
            ], [
                'Contain dampak.',
                'Preserve log dan evidence.',
                'Identifikasi scope dan root cause.',
                'Pulihkan layanan dan review kontrol.',
            ], [
                'Menghapus file/log yang dianggap sumber masalah.',
                'Mengumumkan kesimpulan sebelum scope jelas.',
            ], 'Incident response yang baik cepat tetapi tetap berbasis evidence.', 25, 'simulation', 'incident'),
        ]);

        $this->seedModule($program, 'sca-m08', 'Operasional Infrastruktur dan Troubleshooting', 'Mendiagnosis masalah aplikasi tanpa melakukan perubahan berisiko secara acak.', 8, [
            $this->lesson('Troubleshooting Berlapis', 'Membedakan masalah browser, aplikasi, queue, database, storage, dan reverse proxy.', [
                'HTTP/HTTPS, session, queue, storage, dan database memiliki gejala berbeda.',
                'Log aplikasi dan browser console memberi evidence awal.',
                'Perubahan server harus mengikuti diagnosis, bukan trial-and-error.',
            ], [
                'Reproduksi masalah.',
                'Catat waktu, user, route, dan error.',
                'Periksa log/console yang relevan.',
                'Uji hipotesis paling kecil dampaknya.',
            ], [
                'Restart semua service sebagai langkah pertama.',
                'Mengubah permission folder secara luas tanpa diagnosis.',
            ], 'Troubleshooting yang baik mengurangi variabel dan mempertahankan evidence.', 25, 'guide', 'troubleshooting'),
            $this->lesson('Queue, Notification, dan Email', 'Memahami proses async dan cara mengecek kegagalannya.', [
                'Email/notifikasi dapat diproses queue terpisah.',
                'Status aplikasi “terkirim” perlu dibedakan dari delivery akhir provider.',
                'Retry harus menghindari duplikasi yang merugikan.',
            ], [
                'Periksa worker aktif dan queue yang diproses.',
                'Periksa failed jobs/log.',
                'Verifikasi konfigurasi provider.',
                'Retry secara terkontrol.',
            ], [
                'Menjalankan banyak worker tanpa memahami queue order.',
                'Menganggap tidak ada exception berarti email pasti sampai.',
            ], 'Observability queue dibutuhkan untuk membedakan accepted, processed, dan delivered.', 20, 'content', 'queue'),
            $this->lesson('Backup, Restore, dan Disaster Recovery', 'Menilai kesiapan pemulihan aplikasi dan data.', [
                'Backup harus mencakup database dan file yang relevan.',
                'Restore test membuktikan backup dapat digunakan.',
                'RPO/RTO membantu menentukan frekuensi backup dan target pemulihan.',
            ], [
                'Pastikan backup terbaru tersedia.',
                'Lakukan restore test berkala.',
                'Dokumentasikan dependency dan langkah recovery.',
                'Setelah recovery, verifikasi fungsi kritis.',
            ], [
                'Mengandalkan snapshot tanpa pernah restore test.',
                'Melakukan restore production tanpa backup kondisi terakhir.',
            ], 'Backup adalah input; kemampuan restore yang teruji adalah kontrol sebenarnya.', 30, 'practice', 'dr'),
        ]);
    }
}
