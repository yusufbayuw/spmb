<?php

namespace Database\Seeders\Training\Programs;

use Database\Seeders\Training\CurriculumSeeder;

class AdmissionOperatorTrainingSeeder extends CurriculumSeeder
{
    public function run(): void
    {
        $program = $this->ensureTrainingProgram([
            'code' => 'TRN-TU',
            'name' => 'Pelatihan Operator SPMB',
            'description' => 'Kurikulum operasional TU/operator untuk menangani data pendaftar, verifikasi, pembayaran, tes, status, eskalasi, dan privasi.',
            'target_role' => 'tu',
            'version' => '1.0',
            'sort_order' => 3,
        ]);

        $this->ensureCertificationProgram($program, [
            'code' => 'SCAO',
            'name' => 'SPMB Certified Admission Operator',
            'description' => 'Sertifikasi kompetensi Operator Penerimaan SPMB.',
            'passing_score' => 80,
            'question_count' => 25,
            'time_limit_minutes' => 35,
            'max_attempts' => 3,
            'cooldown_hours' => 24,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'practical_passing_score' => 80,
            'theory_weight' => 40,
            'practical_weight' => 60,
            'valid_months' => 24,
            'sort_order' => 3,
        ]);

        $modules = [
            ['scao-m01','Orientasi Operasional TU','Memahami scope pekerjaan harian, dashboard, dan batas kewenangan TU.',[
                $this->lesson('Peran Operator/TU','Menjalankan verifikasi dan pekerjaan operasional tanpa mengambil alih kewenangan Admin Unit.',[
                    'TU bekerja pada unitnya.',
                    'TU memproses data, bukan mendesain kebijakan penerimaan.',
                    'Kasus di luar scope harus dieskalasikan.',
                ],['Periksa unit akun.','Kenali antrian/verifikasi yang menjadi tugas.','Gunakan jalur eskalasi saat menemukan konfigurasi bermasalah.'],['Mengubah konfigurasi agar kasus bisa lolos.','Menggunakan akun Admin Unit.'],'TU menjaga kualitas eksekusi proses yang sudah dikonfigurasi.',20,'guide','role'),
                $this->lesson('Membaca Status dan Aksi Selanjutnya','Memahami current stage sebelum melakukan tindakan.',[
                    'Status registration menunjukkan posisi proses.',
                    'Aksi yang tersedia mengikuti workflow.',
                ],['Buka registration.','Baca status dan requirement outstanding.','Lakukan hanya aksi yang relevan.'],['Melakukan verifikasi tanpa melihat stage.','Memaksa stage karena pendaftar meminta.'],'Selalu mulai dari state dan requirement aktual.',15,'content','status'),
                $this->lesson('Eskalasi yang Berkualitas','Menyampaikan masalah kepada Admin Unit/Super Admin dengan evidence yang cukup.',[
                    'Screenshot saja sering tidak cukup.',
                    'Registration number, waktu, route, dan pesan error membantu diagnosis.',
                ],['Catat identitas kasus yang aman.','Catat langkah reproduksi.','Sertakan error yang relevan.'],['Mengirim data pribadi berlebihan.','Menyampaikan “error” tanpa langkah reproduksi.'],'Eskalasi harus cukup spesifik untuk ditindaklanjuti tanpa memperluas paparan data.',15,'content','escalation'),
            ]],
            ['scao-m02','Validasi Data Pendaftar','Memeriksa konsistensi data identitas dan informasi dasar pendaftar.',[
                $this->lesson('Validasi Data Pendaftar','Menentukan apakah data cukup dan konsisten untuk melanjutkan proses.',[
                    'Validasi berbeda dari mengubah data tanpa bukti.',
                    'NIK, nama, tanggal lahir, kontak, dan alamat perlu diperiksa sesuai SOP.',
                ],['Periksa data inti.','Bandingkan dengan dokumen bila diperlukan.','Tandai masalah dan minta perbaikan melalui jalur resmi.'],['Memperbaiki data berdasarkan asumsi.','Mengabaikan mismatch karena terlihat kecil.'],'Validasi memastikan data yang diproses selanjutnya dapat dipercaya.',25,'guide','overview'),
                $this->lesson('Koreksi Data dan Evidence','Memproses koreksi berdasarkan sumber yang dapat dipertanggungjawabkan.',[
                    'Koreksi harus memiliki dasar.',
                    'Data verified tidak seharusnya diubah sembarangan.',
                ],['Identifikasi field salah.','Periksa evidence.','Gunakan prosedur koreksi.','Catat/eskalasi bila kewenangan terbatas.'],['Mengubah data karena chat informal.','Mengoreksi banyak field sekaligus tanpa review.'],'Koreksi data harus traceable dan proporsional.',20,'guide','correction'),
                $this->lesson('Data Orang Tua/Wali dan Kontak','Menjaga akurasi data pihak yang dapat dihubungi tanpa menyebarkannya.',[
                    'Kontak dipakai untuk kebutuhan penerimaan.',
                    'Data orang tua/wali termasuk bagian dari data pribadi.',
                ],['Periksa kelengkapan.','Gunakan kontak sesuai keperluan.','Hindari menyalin ke kanal pribadi.'],['Menggunakan nomor untuk tujuan di luar penerimaan.','Menyimpan daftar kontak di perangkat pribadi.'],'Gunakan data kontak hanya untuk tujuan operasional yang sah.',15,'content','parent'),
            ]],
            ['scao-m03','Dokumen Pendaftar','Memverifikasi berkas private secara konsisten dan aman.',[
                $this->lesson('Membaca Requirement Dokumen','Mengetahui dokumen apa yang wajib dan status yang mungkin.',[
                    'Requirement berasal dari konfigurasi unit.',
                    'Dokumen dapat pending, verified, atau rejected sesuai proses.',
                ],['Periksa daftar requirement.','Pastikan file sesuai jenis.','Lanjutkan ke verifikasi.'],['Meminta dokumen tambahan di luar konfigurasi tanpa arahan.','Menganggap file uploaded otomatis valid.'],'Upload hanya berarti file tersedia; validitas tetap perlu diperiksa.',20,'guide','requirements'),
                $this->lesson('Verifikasi dan Penolakan Dokumen','Memberi keputusan verifikasi yang jelas dan dapat diperbaiki.',[
                    'Reject harus punya alasan yang berguna.',
                    'Dokumen verified tidak boleh diminta upload ulang tanpa alasan.',
                ],['Buka file private.','Periksa isi/ketepatan.','Verify atau reject dengan alasan.'],['Menolak dengan alasan “salah” tanpa detail.','Mengunduh dokumen ke perangkat tanpa kebutuhan.'],'Verifikasi harus konsisten, aman, dan komunikatif.',25,'guide','verification'),
                $this->lesson('Keamanan Upload dan File','Memahami bahwa file applicant diperlakukan sebagai input tidak tepercaya.',[
                    'Validasi extension/MIME dan malware scan membantu mengurangi risiko.',
                    'File disimpan private dan diakses melalui authorization.',
                ],['Gunakan viewer/route resmi.','Laporkan file yang gagal diproses.','Jangan menjalankan file applicant.'],['Memindahkan file ke folder public.','Membuka macro/attachment mencurigakan secara lokal.'],'File applicant harus diperlakukan sebagai untrusted content.',20,'content','security'),
            ]],
            ['scao-m04','Verifikasi Dokumen dan Pembayaran','Menangani dua jenis verifikasi yang paling sering dilakukan TU.',[
                $this->lesson('Verifikasi Dokumen dan Pembayaran','Membedakan evidence dokumen dengan evidence finansial serta aturan masing-masing.',[
                    'Dokumen dan payment punya state serta verifier sendiri.',
                    'Setiap keputusan harus sesuai evidence.',
                ],['Selesaikan verifikasi dokumen.','Periksa VA/nominal/bukti pembayaran.','Lakukan keputusan pada resource yang tepat.'],['Meloloskan payment karena dokumen lengkap.','Menganggap screenshot transfer selalu final.'],'Setiap domain diverifikasi berdasarkan evidence domain tersebut.',25,'guide','overview'),
                $this->lesson('Virtual Account dan Status Pembayaran','Memahami hubungan VA, amount, proof, dan verification.',[
                    'VA dapat berasal dari pool.',
                    'Nominal dan reference harus cocok sesuai konfigurasi.',
                ],['Periksa registration dan VA.','Periksa amount/status.','Verifikasi hanya jika evidence sesuai.'],['Memakai VA milik unit/program lain.','Mengubah amount agar terlihat cocok.'],'Validasi pembayaran harus menjaga integritas finansial.',25,'guide','payment'),
                $this->lesson('Bukti Pembayaran Bermasalah','Menangani bukti tidak terbaca, salah nominal, atau tidak sesuai tanpa bypass.',[
                    'Reject/eskalasi lebih aman daripada asumsi.',
                    'Alasan membantu pendaftar memperbaiki bukti.',
                ],['Identifikasi mismatch.','Berikan alasan jelas.','Minta perbaikan atau eskalasi sesuai SOP.'],['Verify agar pendaftar bisa lanjut lalu diperbaiki nanti.','Mengandalkan chat pribadi sebagai satu-satunya evidence.'],'Jangan mengorbankan integritas workflow demi kecepatan satu kasus.',20,'simulation','payment-issue'),
            ]],
            ['scao-m05','Tes dan Penjadwalan','Membantu operasi test tanpa menimbulkan konflik kapasitas atau jadwal.',[
                $this->lesson('Memahami Tes dan Sesi','Membedakan jenis tes dengan sesi pelaksanaan.',[
                    'Tes mendefinisikan aktivitas; sesi mendefinisikan waktu/kapasitas.',
                    'Booking harus sesuai availability.',
                ],['Periksa tes yang wajib.','Periksa session dan booking.','Bantu pendaftar sesuai opsi tersedia.'],['Memindahkan booking tanpa melihat kapasitas.','Menjanjikan slot di luar sistem.'],'Jadwal tes harus mengikuti data session aktual.',20,'content','sessions'),
                $this->lesson('Kartu Tes dan Informasi Peserta','Menjaga agar informasi yang dicetak sesuai registration dan sesi.',[
                    'Kartu tes adalah representasi data saat itu.',
                    'QR/verification membantu validasi dokumen.',
                ],['Pastikan registration benar.','Periksa sesi.','Gunakan route cetak resmi.'],['Mengedit file cetak manual.','Mengirim kartu peserta lain.'],'Dokumen cetak harus berasal dari state aplikasi yang benar.',15,'guide','card'),
                $this->lesson('Perubahan Jadwal dan Eskalasi','Menangani permintaan perubahan tanpa melanggar kapasitas/kebijakan.',[
                    'TU tidak selalu berwenang menambah sesi atau kapasitas.',
                    'Perubahan massal perlu Admin Unit.',
                ],['Periksa alasan.','Cari opsi tersedia.','Eskalasi jika perlu perubahan konfigurasi.'],['Menambah kapasitas melalui workaround.','Memindahkan banyak peserta tanpa persetujuan.'],'TU mengoperasikan sesi yang dikonfigurasi; perubahan kebijakan dieskalasikan.',20,'simulation','change'),
            ]],
            ['scao-m06','Tes, Status, dan Eskalasi','Mengelola hasil tes dan perubahan status sesuai workflow.',[
                $this->lesson('Tes, Status, dan Eskalasi','Menghubungkan test result dengan stage tanpa membuat keputusan seleksi di luar kewenangan.',[
                    'Input hasil tes berbeda dari keputusan accepted/rejected.',
                    'Stage transition mengikuti workflow.',
                ],['Masukkan hasil pada resource yang tepat.','Review data sebelum save.','Pastikan stage bergerak sesuai rule.'],['Mengubah hasil agar sesuai keputusan yang sudah diasumsikan.','Menetapkan hasil seleksi jika bukan kewenangan.'],'Pisahkan pencatatan evidence dari keputusan seleksi.',25,'guide','overview'),
                $this->lesson('Kesalahan Input Hasil','Mengoreksi hasil tes secara traceable.',[
                    'Koreksi harus berdasarkan evidence.',
                    'Perubahan sesudah digunakan dalam seleksi memiliki dampak lebih besar.',
                ],['Hentikan proses lanjutan bila perlu.','Konfirmasi evidence.','Koreksi/eskalasi sesuai SOP.'],['Mengubah nilai diam-diam.','Menghapus hasil lama tanpa jejak.'],'Koreksi hasil harus dapat dijelaskan dan ditelusuri.',20,'simulation','result-correction'),
                $this->lesson('Kasus Tidak Normal','Menangani no-show, file gagal, pembayaran tertunda, atau mismatch data.',[
                    'Exception tidak otomatis berarti bypass.',
                    'Eskalasi dengan kategori yang benar mempercepat penyelesaian.',
                ],['Klasifikasikan kasus.','Catat state dan evidence.','Gunakan mekanisme resmi atau eskalasi.'],['Memaksa stage untuk menutup tiket.','Menyuruh pendaftar membuat akun baru tanpa analisis.'],'Jaga state aplikasi tetap menggambarkan kenyataan.',20,'content','exceptions'),
            ]],
            ['scao-m07','Komunikasi dan Layanan Pendaftar','Memberikan bantuan tanpa membuat janji yang bertentangan dengan sistem/kebijakan.',[
                $this->lesson('Menjawab Pertanyaan Berdasarkan State','Menggunakan data sistem untuk memberi jawaban yang konsisten.',[
                    'Jawaban harus merujuk status aktual.',
                    'Kebijakan tetap mengacu informasi resmi.',
                ],['Identifikasi registration.','Baca current stage dan outstanding action.','Berikan instruksi yang sesuai.'],['Menebak status dari screenshot lama.','Menjanjikan kelulusan atau pengecualian.'],'Bantuan operasional harus faktual dan berbasis state.',20,'guide','support'),
                $this->lesson('Penggunaan WhatsApp/Email yang Aman','Membatasi data yang dibagikan melalui kanal komunikasi.',[
                    'Kanal komunikasi bukan pengganti record aplikasi.',
                    'Pesan sebaiknya tidak memuat data pribadi berlebihan.',
                ],['Gunakan template/pesan seperlunya.','Arahkan tindakan penting kembali ke aplikasi.','Catat keputusan di sistem.'],['Mengirim dokumen applicant melalui grup.','Menggunakan chat sebagai satu-satunya approval.'],'Komunikasi membantu proses; sistem tetap menjadi sumber record operasional.',15,'content','channels'),
                $this->lesson('Menghadapi Tekanan untuk Bypass','Menolak shortcut yang merusak integritas proses secara profesional.',[
                    'Tekanan waktu tidak menghapus requirement verifikasi.',
                    'Exception kebijakan perlu otorisasi yang tepat.',
                ],['Jelaskan state dan requirement.','Eskalasi permintaan pengecualian.','Jangan ubah data tanpa dasar.'],['Verify karena diminta pihak yang tidak berwenang.','Memindahkan stage agar masalah “selesai”.'],'Integritas operator diuji justru pada kasus yang meminta shortcut.',20,'simulation','pressure'),
            ]],
            ['scao-m08','Privasi, Keamanan, dan Audit Operator','Menjaga akun serta data yang diproses setiap hari.',[
                $this->lesson('Need-to-Know untuk Operator','Membatasi akses pada data yang diperlukan untuk tugas.',[
                    'TU memiliki akses operasional, bukan kebebasan melihat semua data.',
                    'Export dan download memperluas paparan.',
                ],['Buka hanya record yang sedang ditangani.','Hindari export jika tidak perlu.','Gunakan perangkat kerja yang sesuai.'],['Membuka data karena penasaran.','Menyimpan file applicant untuk referensi pribadi.'],'Akses teknis harus selalu mengikuti kebutuhan kerja.',20,'content','need-to-know'),
                $this->lesson('Akun Personal dan Jejak Audit','Memahami hubungan akun personal dengan akuntabilitas tindakan.',[
                    'Audit actor hanya bermakna jika akun tidak dibagi.',
                    'Password dan session harus dijaga.',
                ],['Gunakan akun sendiri.','Logout bila meninggalkan perangkat shared.','Laporkan akses mencurigakan.'],['Berbagi password saat shift berganti.','Menggunakan satu akun TU bersama.'],'Akun personal melindungi user sekaligus organisasi melalui audit yang jelas.',15,'content','account'),
                $this->lesson('Mengenali Insiden dan Melapor','Mengidentifikasi kejadian yang perlu segera dieskalasikan.',[
                    'Data unit lain terlihat, file salah applicant, atau akses tidak sah adalah sinyal serius.',
                    'Evidence perlu dipertahankan.',
                ],['Stop tindakan.','Catat waktu dan gejala.','Laporkan melalui jalur resmi.'],['Mencoba memperbaiki database sendiri.','Menghapus bukti kesalahan.'],'Respons awal operator harus membatasi dampak dan menjaga evidence.',20,'simulation','incident'),
            ]],
            ['scao-m09','Quality Check dan Penutupan Tugas','Menjaga kualitas pekerjaan harian serta handover shift/periode.',[
                $this->lesson('Self-Check Sebelum Menyimpan Keputusan','Mengurangi error manusia pada verifikasi berulang.',[
                    'Nama applicant, jenis document, amount, dan unit mudah tertukar saat volume tinggi.',
                ],['Konfirmasi applicant.','Konfirmasi object yang diverifikasi.','Bandingkan evidence.','Baru simpan keputusan.'],['Bekerja terlalu cepat dengan banyak tab.','Mengandalkan posisi baris tanpa baca identitas.'],'Micro-check sebelum save jauh lebih murah daripada koreksi setelah workflow bergerak.',20,'practice','self-check'),
                $this->lesson('Handover Pekerjaan','Meneruskan kasus outstanding tanpa berbagi akun.',[
                    'Handover perlu status, next action, dan evidence yang cukup.',
                    'Akun tetap personal.',
                ],['Catat kasus outstanding.','Jelaskan next action.','Serahkan melalui kanal resmi.'],['Memberikan password ke shift berikutnya.','Handover tanpa menyebut registration/status.'],'Handover memindahkan konteks pekerjaan, bukan identitas akun.',15,'guide','handover'),
                $this->lesson('Menutup Antrian Operasional','Membedakan kasus selesai, menunggu pendaftar, dan perlu eskalasi.',[
                    'Tidak semua outstanding harus dipaksa selesai hari itu.',
                    'Status harus mencerminkan kenyataan.',
                ],['Kelompokkan antrian.','Selesaikan yang evidence-nya lengkap.','Sisakan dengan status/notes yang jelas.'],['Verify agar antrian kosong.','Menghapus kasus yang sulit.'],'Kualitas state lebih penting daripada dashboard terlihat kosong.',20,'content','queue-close'),
            ]],
        ];

        foreach ($modules as $index => [$key, $title, $description, $lessons]) {
            $this->seedModule($program, $key, $title, $description, $index + 1, $lessons);
        }
    }
}
