<?php

namespace Database\Seeders\Training\Programs;

use Database\Seeders\Training\CurriculumSeeder;

class UnitAdministratorTrainingSeeder extends CurriculumSeeder
{
    public function run(): void
    {
        $program = $this->ensureTrainingProgram([
            'code' => 'TRN-UNIT',
            'name' => 'Pelatihan Administrator Unit SPMB',
            'description' => 'Kurikulum lengkap Admin Unit untuk menyiapkan, membuka, menjalankan, mengendalikan, dan menutup proses penerimaan pada unitnya.',
            'target_role' => 'admin_unit',
            'version' => '1.0',
            'sort_order' => 2,
        ]);

        $this->ensureCertificationProgram($program, [
            'code' => 'SCUA',
            'name' => 'SPMB Certified Unit Administrator',
            'description' => 'Sertifikasi kompetensi Administrator Unit SPMB.',
            'passing_score' => 80,
            'question_count' => 30,
            'time_limit_minutes' => 45,
            'max_attempts' => 3,
            'cooldown_hours' => 24,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'practical_passing_score' => 80,
            'theory_weight' => 40,
            'practical_weight' => 60,
            'valid_months' => 24,
            'sort_order' => 2,
        ]);

        $modules = [
            ['scua-m01','Orientasi Admin Unit','Memahami tanggung jawab, scope unit, dashboard, dan batas kewenangan.',[
                $this->lesson('Peran dan Tanggung Jawab Admin Unit','Menjelaskan pekerjaan Admin Unit dari konfigurasi sampai penutupan penerimaan.',[
                    'Admin Unit mengelola konfigurasi dan operasi dalam unitnya.',
                    'Data unit lain tidak boleh menjadi bagian dari pekerjaan normal.',
                    'Perubahan sensitif tetap perlu review dan audit.',
                ],['Periksa unit pada akun.','Kenali menu konfigurasi, operasional, laporan, dan sertifikasi.','Pisahkan tugas Admin Unit dari TU.'],['Menggunakan akun Super Admin untuk pekerjaan unit.','Berbagi akun dengan TU.'],'Admin Unit adalah pemilik operasional unit, bukan administrator global.',20,'guide','role'),
                $this->lesson('Membaca Dashboard Operasional','Menggunakan dashboard untuk mengenali kondisi penerimaan sebelum bertindak.',[
                    'Angka agregat perlu dibaca bersama status opening dan workflow.',
                    'Anomali harus ditelusuri ke data sumber.',
                ],['Periksa opening aktif.','Periksa jumlah registration dan stage.','Cari exception sebelum membuat perubahan massal.'],['Menganggap dashboard sebagai sumber tunggal diagnosis.','Mengubah konfigurasi hanya karena satu angka terlihat aneh.'],'Dashboard adalah alat orientasi; keputusan perlu verifikasi data terkait.',15,'content','dashboard'),
                $this->lesson('Scope Unit dan Eskalasi','Menentukan kapan masalah dapat diselesaikan sendiri dan kapan harus dieskalasikan.',[
                    'Konfigurasi unit berada pada kewenangan Admin Unit.',
                    'Masalah global, permission lintas unit, dan infrastruktur perlu eskalasi.',
                ],['Klasifikasikan masalah.','Selesaikan yang berada dalam scope.','Kirim evidence saat eskalasi.'],['Meminta Super Admin mengerjakan semua masalah kecil.','Mencoba mengubah area global tanpa kewenangan.'],'Eskalasi yang baik membawa evidence dan batas scope yang jelas.',15,'content','scope'),
            ]],
            ['scua-m02','Konfigurasi Penerimaan Unit','Menyiapkan profil, informasi publik, persetujuan, dan konfigurasi penerimaan unit.',[
                $this->lesson('Konfigurasi Penerimaan Unit','Memahami kumpulan konfigurasi yang membentuk pengalaman pendaftar pada unit.',[
                    'Profil penerimaan, FAQ, persetujuan, opening, pathway, dan workflow saling terkait.',
                    'Rich text perlu tampil konsisten pada admin dan pendaftar.',
                ],['Lengkapi profil penerimaan.','Periksa kontak, FAQ, dan konten persetujuan.','Preview sisi pendaftar sebelum publish.'],['Mengedit rich text tanpa mengecek hasil render.','Membuka pendaftaran ketika informasi publik belum lengkap.'],'Konfigurasi unit harus diperlakukan sebagai satu paket pengalaman penerimaan.',25,'guide','overview'),
                $this->lesson('Profil Penerimaan dan Informasi Publik','Menyusun informasi yang jelas dan tidak menyesatkan pendaftar.',[
                    'Informasi publik perlu konsisten dengan kebijakan unit.',
                    'Tanggal, biaya, tahapan, dan kontak adalah informasi kritis.',
                ],['Perbarui profil.','Pastikan format rich text terbawa.','Cek homepage/unit admission page.'],['Membiarkan tanggal lama.','Mengandalkan banner tanpa memperbarui teks utama.'],'Publikasi yang konsisten mengurangi pertanyaan dan kesalahan pendaftar.',20,'content','profile'),
                $this->lesson('Persetujuan dan Confirmation Content','Mengelola persetujuan dengan bahasa yang dapat dipahami dan snapshot yang terjaga.',[
                    'Persetujuan bukan formalitas UI.',
                    'Konten yang diterima perlu dapat dibuktikan kembali melalui snapshot.',
                ],['Tulis isi persetujuan.','Tentukan kalimat konfirmasi.','Uji modal pada flow pendaftaran.'],['Mengubah makna persetujuan setelah penerimaan berjalan tanpa review.','Membuat kalimat konfirmasi ambigu.'],'Persetujuan harus jelas, dapat dibaca, dan konsisten dengan proses.',20,'guide','consent'),
            ]],
            ['scua-m03','Pembukaan, Jalur, dan Program Studi','Mengelola opening serta variasi jalur/program yang tersedia bagi pendaftar.',[
                $this->lesson('Lifecycle Pembukaan Pendaftaran','Menggunakan status opening sesuai kebutuhan operasional.',[
                    'Draft untuk persiapan, scheduled untuk jadwal, open untuk menerima, paused untuk jeda sementara, closed untuk penutupan, archived untuk histori.',
                    'Pause tidak sama dengan close.',
                ],['Siapkan opening sebagai draft.','Review tanggal dan konfigurasi.','Open atau schedule sesuai rencana.','Gunakan pause untuk penghentian sementara.'],['Menutup opening ketika hanya ingin maintenance singkat.','Menghapus opening yang sudah memiliki pendaftar.'],'Status opening adalah kontrol visibilitas dan lifecycle yang harus dipilih tepat.',25,'guide','opening'),
                $this->lesson('Jalur Pendaftaran','Mengatur jalur tanpa mencampur aturan antar kelompok pendaftar.',[
                    'Pathway dapat membedakan aturan, kuota, atau proses.',
                    'Nama dan deskripsi jalur harus mudah dipahami.',
                ],['Definisikan jalur.','Kaitkan ke opening yang benar.','Cek requirement dan workflow yang berlaku.'],['Membuat jalur duplikat karena perubahan nama.','Mengubah jalur existing tanpa menilai dampak registration.'],'Jalur adalah bagian business rule, bukan sekadar label.',20,'content','pathway'),
                $this->lesson('Program Studi pada Higher Education/Mixed','Mengelola konfigurasi program studi ketika mode mendukungnya.',[
                    'Program studi dapat memiliki konfigurasi yang berbeda.',
                    'Kode program digunakan pada beberapa integrasi/pool.',
                ],['Pastikan mode mendukung.','Tetapkan program studi dan kode.','Review konfigurasi per program sebelum publish.'],['Menggunakan program studi pada K12 tanpa kebutuhan.','Mengganti kode program saat transaksi sudah berjalan tanpa review.'],'Program studi memberi granularity tambahan dan harus dikelola konsisten.',20,'content','study-program'),
            ]],
            ['scua-m04','Form Pendaftaran dan Data Pendaftar','Mengelola kebutuhan data tanpa mengumpulkan data berlebihan.',[
                $this->lesson('Field Pendaftaran dan Data Inti','Menentukan data yang dibutuhkan unit dari pendaftar.',[
                    'Field inti memiliki fungsi operasional dan validasi.',
                    'Custom field harus punya tujuan jelas.',
                    'Data pribadi perlu diminimalkan.',
                ],['Review field inti.','Tambahkan custom field hanya bila diperlukan.','Uji required/optional dan tampilan mobile.'],['Membuat custom field duplikat dari field inti.','Mengumpulkan data “untuk berjaga-jaga”.'],'Form yang baik cukup lengkap untuk proses namun tidak berlebihan.',20,'guide','fields'),
                $this->lesson('Alamat, Identitas, dan Data Orang Tua','Memahami kualitas data identitas yang berdampak pada verifikasi.',[
                    'NIK dan identitas harus diperlakukan hati-hati.',
                    'Alamat terdiri dari beberapa bagian yang perlu konsisten.',
                    'Data orang tua/wali dipakai sesuai kebutuhan penerimaan.',
                ],['Periksa format field.','Pastikan label mudah dipahami.','Gunakan data hanya sesuai tugas.'],['Menyebarkan screenshot identitas melalui chat personal.','Mengubah data tanpa sumber koreksi yang jelas.'],'Kualitas data dan privasi harus dijaga bersama.',20,'content','identity'),
                $this->lesson('Draft, Koreksi, dan Publikasi Konfigurasi Form','Mengubah form dengan aman ketika penerimaan belum atau sudah berjalan.',[
                    'Draft memungkinkan review sebelum perubahan diterapkan.',
                    'Perubahan field ketika sudah ada registration perlu dianalisis dampaknya.',
                ],['Buat perubahan sebagai draft bila tersedia.','Uji form baru.','Pastikan data existing tetap dapat dibaca.','Publish setelah review.'],['Menghapus field yang sudah berisi data tanpa migrasi.','Publish perubahan besar tanpa uji mobile.'],'Perubahan form harus backward-compatible dengan data existing.',25,'guide','form-change'),
            ]],
            ['scua-m05','Workflow dan Tahapan Pendaftaran','Merancang urutan proses yang konsisten dengan kebijakan unit.',[
                $this->lesson('Mendesain Workflow Unit','Menyusun tahap dari validasi sampai completion tanpa jalur buntu.',[
                    'Workflow adalah business process yang dapat berbeda antar unit.',
                    'Stage perlu urutan, prasyarat, dan aksi berikutnya yang jelas.',
                ],['Petakan proses nyata.','Cocokkan dengan stage yang tersedia.','Uji happy path dan exception path.'],['Membuat stage hanya karena tersedia.','Mengubah urutan saat banyak registration sedang berjalan tanpa review.'],'Workflow harus merepresentasikan proses nyata dan dapat dijalankan dari awal sampai selesai.',25,'guide','design'),
                $this->lesson('Aksi Selanjutnya dan Tahapan Pendaftaran','Menjaga agar pendaftar mendapat petunjuk yang relevan.',[
                    'Aksi selanjutnya harus sesuai current stage.',
                    'Tahapan memberi konteks posisi pendaftar.',
                ],['Periksa konfigurasi blok informasi.','Pastikan stage aktif tampil dengan benar.','Uji pada beberapa status registration.'],['Menampilkan semua blok sekaligus tanpa prioritas.','Memberi CTA yang tidak dapat dilakukan.'],'UI harus mengikuti workflow, bukan menambah kebingungan.',15,'content','next-action'),
                $this->lesson('Mengubah Workflow yang Sudah Berjalan','Melakukan perubahan dengan memperhitungkan registration existing.',[
                    'Registration existing mungkin berada pada stage lama.',
                    'Perubahan besar perlu mapping atau strategi transisi.',
                ],['Inventaris stage aktif.','Tentukan dampak ke registration existing.','Lakukan perubahan terkontrol.','Review kasus exception.'],['Menghapus stage aktif tanpa strategi.','Memindahkan semua registration massal tanpa validasi.'],'Workflow change adalah migrasi proses dan perlu perlakuan seperti perubahan data.',25,'simulation','workflow-change'),
            ]],
            ['scua-m06','Dokumen, Pembayaran, dan Tes','Mengelola tiga domain operasional yang paling sering menentukan kelanjutan pendaftaran.',[
                $this->lesson('Dokumen, Pembayaran, dan Tes','Memahami dependensi dokumen, payment, dan test terhadap workflow.',[
                    'Dokumen wajib harus disesuaikan kebutuhan unit.',
                    'Payment verification tidak boleh dilewati hanya karena bukti di luar sistem.',
                    'Tes membutuhkan konfigurasi, jadwal, dan hasil yang konsisten.',
                ],['Definisikan requirement dokumen.','Atur payment/VA bila digunakan.','Konfigurasikan tes dan sesi.','Uji transisi antar tahap.'],['Membuat requirement yang tidak digunakan.','Mengandalkan bukti WhatsApp tanpa pencatatan resmi.'],'Ketiga domain ini harus terintegrasi dengan workflow dan audit.',30,'guide','overview'),
                $this->lesson('Persyaratan Dokumen dan Verifikasi','Menentukan dokumen apa yang wajib dan bagaimana penolakan dikomunikasikan.',[
                    'Dokumen pendaftar disimpan private.',
                    'Rejection reason membantu perbaikan oleh pendaftar.',
                    'Requirement dapat berbeda menurut konfigurasi.',
                ],['Tentukan dokumen dan format.','Uji upload.','Pastikan TU melihat requirement yang tepat.'],['Mewajibkan dokumen tanpa tujuan.','Membuka file melalui jalur public.'],'Dokumen harus minimal, private, dan dapat diverifikasi dengan alasan yang jelas.',20,'content','documents'),
                $this->lesson('Virtual Account, Pembayaran, dan Tes','Menyusun konfigurasi finansial dan test tanpa mencampur kewenangan.',[
                    'VA pool harus sesuai unit/program bila digunakan.',
                    'Payment verification adalah tindakan operasional sensitif.',
                    'Test session perlu kapasitas dan jadwal yang benar.',
                ],['Siapkan VA/config payment.','Uji pembayaran dan receipt.','Buat test dan session.','Uji booking serta kapasitas.'],['Meminjam VA program lain.','Mengubah kapasitas setelah penuh tanpa komunikasi.'],'Payment dan test harus konsisten dengan konfigurasi unit dan data aktual.',25,'guide','payment-test'),
            ]],
            ['scua-m07','Kuota, Tes, dan Penjadwalan','Menjaga kapasitas dan jadwal tes tetap konsisten saat trafik meningkat.',[
                $this->lesson('Membuat Tes dan Sesi','Menentukan tes, tanggal, lokasi, serta kapasitas sesi.',[
                    'Test dan session perlu dibedakan.',
                    'Kapasitas membatasi booking dan mencegah overbooking.',
                ],['Buat jenis tes.','Buat sesi dengan waktu dan kapasitas.','Uji pilihan pendaftar.'],['Membuat sesi tanpa kapasitas realistis.','Mengubah waktu setelah banyak booking tanpa pemberitahuan.'],'Sesi tes adalah resource berkapasitas yang harus dikelola konsisten.',20,'guide','sessions'),
                $this->lesson('Concurrency dan Kapasitas','Memahami mengapa booking bersamaan perlu kontrol transaksi.',[
                    'Dua pendaftar dapat memilih slot terakhir hampir bersamaan.',
                    'Sistem harus mengandalkan transaksi/locking, bukan asumsi urutan klik.',
                ],['Monitor kapasitas.','Hindari koreksi manual massal.','Jika ada anomali, eskalasi dengan evidence.'],['Menambah kapasitas hanya untuk menutup error data.','Mengubah booking langsung melalui database.'],'Concurrency adalah masalah integritas data, bukan sekadar tampilan.',20,'content','concurrency'),
                $this->lesson('Perubahan Jadwal dan Komunikasi','Mengubah sesi dengan dampak minimal ke pendaftar.',[
                    'Perubahan jadwal setelah booking memiliki dampak komunikasi.',
                    'Notifikasi perlu diverifikasi prosesnya.',
                ],['Identifikasi peserta terdampak.','Ubah jadwal secara resmi.','Kirim/cek notifikasi.','Verifikasi daftar peserta.'],['Mengubah tanggal tanpa melihat booking.','Menganggap email pasti diterima tanpa monitoring.'],'Perubahan jadwal harus mempertimbangkan data dan komunikasi.',20,'simulation','reschedule'),
            ]],
            ['scua-m08','Seleksi dan Penetapan Hasil','Mengelola proses keputusan tanpa mempublikasikan hasil secara prematur.',[
                $this->lesson('Persiapan Seleksi','Memastikan data input seleksi lengkap dan dapat dipertanggungjawabkan.',[
                    'Eligibility dan data tes perlu siap sebelum keputusan.',
                    'Batch membantu pengelolaan kelompok keputusan.',
                ],['Tentukan populasi seleksi.','Periksa data pendukung.','Buat batch bila sesuai.'],['Menyeleksi data yang belum diverifikasi.','Mengubah populasi setelah proses berjalan tanpa catatan.'],'Seleksi yang baik dimulai dari populasi dan input yang jelas.',20,'guide','prep'),
                $this->lesson('Penetapan Hasil','Membedakan keputusan internal dengan status yang dilihat pendaftar.',[
                    'Accepted, rejected, waiting list, atau status lain perlu aturan.',
                    'Penetapan belum selalu berarti publikasi.',
                ],['Review keputusan.','Tetapkan hasil.','Lakukan quality check sebelum publish.'],['Menyimpan keputusan tanpa review.','Menggunakan status sementara sebagai hasil final.'],'Decision state harus jelas sebelum publication state.',20,'content','decision'),
                $this->lesson('Waiting List dan Admission Offer','Mengelola kandidat cadangan dan penawaran penerimaan.',[
                    'Waiting list bukan accepted.',
                    'Admission offer dapat memerlukan respons accept/decline.',
                ],['Tetapkan waiting list.','Terbitkan offer sesuai kebijakan.','Pantau respons dan kuota.'],['Menganggap waiting list otomatis diterima.','Membuat offer melebihi kebijakan tanpa review.'],'Waiting list dan offer memerlukan lifecycle sendiri yang terkontrol.',20,'guide','offer'),
            ]],
            ['scua-m09','Seleksi, Publikasi, dan Daftar Ulang','Menjalankan publikasi hasil dan proses pasca-pengumuman secara terkendali.',[
                $this->lesson('Seleksi, Publikasi, dan Daftar Ulang','Menghubungkan keputusan internal, publikasi, penawaran, dan re-registration.',[
                    'Publication harus dilakukan setelah review.',
                    'Re-registration mengumpulkan tindak lanjut dari peserta yang diterima.',
                ],['Finalisasi keputusan.','Publikasikan hasil.','Pantau admission offer.','Aktifkan proses daftar ulang sesuai workflow.'],['Publikasi sebelum review akhir.','Mengubah hasil tanpa prosedur koreksi.'],'Tahap pasca-pengumuman tetap membutuhkan audit dan kontrol akses.',25,'guide','overview'),
                $this->lesson('Publikasi Hasil','Memastikan hanya hasil yang siap yang ditampilkan kepada pendaftar.',[
                    'Draft/publish adalah kontrol penting.',
                    'Tanggal publikasi dan isi hasil harus konsisten.',
                ],['Review batch/decision.','Publish pada waktu yang ditetapkan.','Verifikasi dari akun pendaftar.'],['Menggunakan data internal sebagai halaman publik.','Publish sebagian tanpa memahami filter.'],'Publication adalah tindakan sensitif yang perlu verifikasi dua sisi.',20,'guide','publish'),
                $this->lesson('Daftar Ulang dan Completion','Menyelesaikan proses accepted sampai enrollment/completed.',[
                    'Re-registration dapat memiliki item/requirement tambahan.',
                    'Completion seharusnya mencerminkan kewajiban yang selesai.',
                ],['Tentukan requirement.','Pantau pemenuhan.','Verifikasi item.','Selesaikan enrollment sesuai kebijakan.'],['Menandai completed hanya agar dashboard bersih.','Menghapus requirement yang sudah digunakan.'],'Completion harus mencerminkan proses nyata, bukan sekadar status akhir.',20,'content','reregistration'),
            ]],
            ['scua-m10','Laporan, Ekspor, dan Audit Unit','Menggunakan data unit untuk operasional tanpa membuka data lintas scope.',[
                $this->lesson('Laporan Operasional Unit','Membaca metrik pendaftaran sesuai unit.',[
                    'Jumlah registration, payment, document, dan status perlu dilihat bersama konteks.',
                    'TU/Admin Unit hanya membutuhkan scope unit.',
                ],['Pilih periode/opening.','Review metrik.','Telusuri anomali ke record sumber.'],['Membandingkan angka tanpa periode yang sama.','Meminta export global untuk kebutuhan unit.'],'Pelaporan harus scoped dan dapat ditelusuri.',20,'guide','reports'),
                $this->lesson('Ekspor Data dengan Aman','Mengelola spreadsheet hasil export sebagai data sensitif.',[
                    'File export berada di luar kontrol akses aplikasi setelah diunduh.',
                    'Kolom yang tidak dibutuhkan sebaiknya tidak dibagikan.',
                ],['Tentukan tujuan.','Gunakan export unit.','Simpan di lokasi aman.','Hapus salinan sementara.'],['Mengirim file penuh melalui grup umum.','Menyimpan banyak versi export di desktop pribadi.'],'Risiko data meningkat ketika export meninggalkan aplikasi.',20,'content','export'),
                $this->lesson('Audit Perubahan Konfigurasi Unit','Menggunakan log untuk menjawab siapa mengubah apa dan kapan.',[
                    'Audit membantu investigasi konfigurasi.',
                    'Perubahan perlu dibaca bersama old/new value.',
                ],['Cari event.','Identifikasi actor dan waktu.','Bandingkan nilai.','Tentukan tindakan koreksi bila perlu.'],['Menghapus log.','Menuduh user tanpa memeriksa context.'],'Audit adalah evidence teknis untuk rekonstruksi perubahan.',20,'guide','audit'),
            ]],
            ['scua-m11','Privasi dan Keamanan Operasional','Melindungi data pendaftar selama pekerjaan harian Admin Unit.',[
                $this->lesson('Data Pribadi dalam Penerimaan','Mengenali data yang perlu perlindungan lebih tinggi.',[
                    'Identitas, dokumen, data orang tua, pembayaran, dan hasil seleksi perlu kontrol.',
                    'Akses harus berdasarkan kebutuhan kerja.',
                ],['Gunakan aplikasi untuk melihat data.','Hindari salinan lokal tidak perlu.','Gunakan kanal resmi untuk eskalasi.'],['Menyimpan foto dokumen di chat pribadi.','Mengunduh seluruh data untuk satu kasus.'],'Minimalkan paparan data sambil tetap memenuhi kebutuhan operasional.',20,'content','privacy'),
                $this->lesson('Akun, Session, dan Perangkat','Menjaga akun admin agar tidak menjadi jalur akses tidak sah.',[
                    'Akun tidak boleh dibagi.',
                    'Perangkat publik/shared memerlukan perhatian session.',
                ],['Gunakan akun personal.','Logout dari perangkat bersama.','Laporkan akses mencurigakan.'],['Menyimpan password di browser publik.','Meminjamkan akun pada staff pengganti.'],'Identitas user di audit hanya bermakna jika akun tidak dibagi.',15,'content','account'),
                $this->lesson('Eskalasi Insiden','Merespons data salah, akses tidak sah, atau publikasi keliru secara cepat.',[
                    'Jangan menghapus evidence.',
                    'Batasi dampak dan catat kronologi.',
                ],['Stop tindakan lanjutan.','Catat waktu dan record terdampak.','Eskalasi ke Super Admin dengan evidence.'],['Mencoba banyak perubahan sebelum melapor.','Menghapus record untuk menyembunyikan kesalahan.'],'Containment dan evidence lebih penting daripada terlihat cepat selesai.',20,'simulation','incident'),
            ]],
            ['scua-m12','Go-Live dan Penutupan Siklus','Menjalankan checklist sebelum opening dan setelah periode penerimaan selesai.',[
                $this->lesson('Checklist Sebelum Go-Live','Memastikan konfigurasi kritis siap sebelum opening dibuka.',[
                    'Profil, tanggal, form, dokumen, payment, test, workflow, consent, dan kontak perlu diperiksa.',
                ],['Review konfigurasi.','Uji satu alur pendaftar.','Uji mobile.','Konfirmasi staff dan support channel.'],['Membuka dulu lalu memperbaiki sambil berjalan.','Menguji hanya dari akun admin.'],'Go-live yang baik memvalidasi pengalaman pendaftar end-to-end.',25,'practice','go-live'),
                $this->lesson('Monitoring Saat Penerimaan Aktif','Mengenali indikator masalah tanpa mengubah konfigurasi secara reaktif.',[
                    'Error upload, payment, queue, dan kapasitas perlu dipantau.',
                    'Perubahan besar sebaiknya tidak dilakukan saat trafik tinggi tanpa kebutuhan.',
                ],['Pantau dashboard dan laporan.','Review exception.','Koordinasikan perubahan kritis.'],['Mengubah workflow setiap ada keluhan tunggal.','Mengabaikan pola error berulang.'],'Monitoring membantu membedakan kasus individual dari masalah sistemik.',20,'content','monitoring'),
                $this->lesson('Menutup dan Mengarsipkan Periode','Mengakhiri siklus tanpa kehilangan histori.',[
                    'Closed menghentikan penerimaan; archived memindahkan dari operasi aktif.',
                    'Data historis tetap berguna untuk audit dan laporan.',
                ],['Close opening.','Selesaikan proses outstanding.','Review laporan akhir.','Archive ketika tidak lagi operasional.'],['Hard delete periode lama.','Archive saat masih ada proses aktif yang belum selesai.'],'Penutupan siklus harus mempertahankan histori dan mengurangi noise operasional.',20,'guide','close'),
            ]],
            ['scua-m13','Terusan dan Orkestrasi Tes Modern','Mengelola prefill Terusan serta alur tes multi-sesi sesuai perilaku aplikasi terkini.',[
                $this->lesson('Data Terusan dan Template Import','Mengelola data siswa terusan tanpa menampilkan mekanisme internal kepada pendaftar.',[
                    'Resource Terusan hanya tersedia bagi Admin Unit sesuai scope unit.',
                    'Template XLSX menyediakan struktur dua tingkat untuk data siswa dan orang tua/wali.',
                    'Matching ke formulir pendaftaran berjalan di belakang layar menggunakan unit, tahun ajaran, NIK, dan tanggal lahir.',
                    'Upload ulang dengan identitas yang sama memperbarui kandidat existing; perubahan identitas utama dapat membentuk kandidat baru.',
                ],['Unduh template resmi dari menu Terusan.','Isi sheet Data Terusan tanpa mengubah header.','Import ke unit dan tahun ajaran yang tepat.','Review jumlah data baru, diperbarui, dan dilewati.','Tangani koreksi identitas dengan hati-hati agar tidak membuat duplikasi.'],['Mengedit struktur header template.','Menganggap upload kedua selalu mengganti seluruh dataset.','Mengubah NIK/tanggal lahir tanpa menilai kemungkinan record baru.','Membagikan file master Terusan ke kanal yang tidak berwenang.'],'Terusan adalah sumber prefill scoped per unit; kualitas identitas menentukan keberhasilan matching dan deduplikasi.',30,'guide','continuation'),
                $this->lesson('Tes Wajib, Konfirmasi Jadwal, dan Kartu Tes','Memahami bahwa pemilihan sesi kini diselesaikan pendaftar untuk seluruh tes wajib lalu dikonfirmasi final.',[
                    'Setiap pilihan sesi tersimpan otomatis, tetapi pemilihan jadwal belum selesai sebelum semua tes wajib memiliki sesi.',
                    'Tes opsional tidak menghalangi penyelesaian jadwal wajib.',
                    'Kartu tes hanya dapat dicetak setelah seluruh tes wajib dipilih dan jadwal dikonfirmasi.',
                    'Konfirmasi jadwal tidak memindahkan stage ke selection; stage tests selesai setelah seluruh hasil tes wajib final.',
                ],['Konfigurasikan seluruh tes wajib dan sesi yang tersedia.','Uji skenario lebih dari satu tes wajib.','Pastikan peserta memilih semua sesi lalu menekan konfirmasi final.','Pastikan kartu tes baru tersedia setelah konfirmasi.','Bedakan penyelesaian jadwal dengan penyelesaian pelaksanaan tes.'],['Menganggap satu booking berarti seluruh tahap tes selesai.','Mencetak kartu sebelum konfirmasi.','Memaksa transisi selection hanya karena jadwal sudah lengkap.'],'Jadwal tes adalah komitmen multi-item: lengkap, dikonfirmasi, lalu dilaksanakan sebelum workflow dapat lanjut.',30,'simulation','multi-test-confirmation'),
                $this->lesson('Perubahan Sesi dan Export Peserta Tes','Mengelola perubahan jadwal setelah ada booking tanpa kehilangan konsistensi dan scope data.',[
                    'Perubahan waktu, lokasi, status, atau booking membatalkan konfirmasi jadwal peserta terdampak.',
                    'Pembatalan sesi melepaskan booking dan meminta peserta memilih sesi baru.',
                    'Export Peserta tersedia per sesi dan harus tetap scoped ke unit serta sesi yang dipilih.',
                    'Daftar peserta hasil export merupakan data sensitif di luar kontrol permission aplikasi setelah diunduh.',
                ],['Periksa jumlah booking sebelum mengubah sesi.','Identifikasi peserta terdampak.','Lakukan perubahan melalui Sesi Tes, bukan database langsung.','Verifikasi notifikasi dan kebutuhan konfirmasi ulang.','Gunakan Export Peserta hanya untuk sesi dan kebutuhan kerja yang relevan.'],['Mengubah jadwal diam-diam tanpa mengecek peserta terdampak.','Menganggap konfirmasi lama tetap valid setelah sesi berubah.','Menggabungkan export lintas sesi/unit tanpa kebutuhan.'],'Perubahan sesi harus memicu rekonsiliasi jadwal; export peserta harus tetap spesifik, scoped, dan terlindungi.',30,'simulation','session-change-export'),
            ]],
        ];

        foreach ($modules as $index => [$key, $title, $description, $lessons]) {
            $this->seedModule($program, $key, $title, $description, $index + 1, $lessons);
        }
    }
}
