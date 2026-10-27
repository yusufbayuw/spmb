# Tes Online Fase 1 — Modul Mandiri

**Status:** draft PR. Deployment/staging belum dilakukan; tidak boleh dianggap production-ready hanya karena CI.

## Prinsip kompatibilitas

- Modul \`OnlineExam\` adalah domain baru; database dipisahkan dari \`admission_tests\`,
  \`admission_test_results\`, \`test_bookings\`, \`test_sessions\`, dan \`unit_configurations\`.
- Hasil tes **tidak** menulis ke \`AdmissionTestResult\`, tidak menentukan kelulusan
  SPMB/PMB, dan tidak mengubah \`registrations.current_stage\`.
- Modul dapat digunakan hanya melalui penugasan pengguna, termasuk akun pendaftar
  tanpa registrasi aktif. Tidak mengambil peserta dari alur workflow secara otomatis.
- Integrasi SPMB merupakan rencana opsional fase berikutnya, membutuhkan
  pemetaan tes, persetujuan staf, idempotency, dan audit; bukan bagian Fase 1.

## Fitur

- 6 keluarga: Pilihan Ganda (1/multi), Benar/Salah, Matching, Short Answer,
  Numerical (toleransi absolut), Essay (koreksi petugas).
- Soal dapat diacak dan pilihan diacak; soal, pilihan, dan kunci dinilai dari
  **snapshot attempt**, bukan bank soal terkini.
- Peserta mengerjakan di panel Pendaftar → Tes Online, yang berisi daftar
  penugasan, timer server, autosave, finalisasi dan status hasil.
- Admin Unit dan Super Admin mengelola bank soal, paket tes, isi paket, penugasan
  dan penilaian esai melalui menu **Tes Online** di panel Admin.
- Skor pilihan ganda multi **semua atau tidak sama sekali**; matching memberi
  skor parsial per pasangan; esai memakai bobot poin manual.
- Pembatasan upaya dan waktu dilaksanakan server dalam transaksi ber-lock.
- Soal yang sudah dikerjakan tidak berubah jika bank soal diedit kemudian.
- Koreksi esai menyimpan siapa yang menilai. Koreksi tidak dapat dilakukan
  ulang setelah status final, dan tidak ada perubahan tahap pendaftaran.

## Cara menggunakan (admin)

1. Di **Bank Soal Online**, buat soal dengan tipe, bobot dan kunci jawaban.
   - MC tunggal: \`options.choices\` kode A/B/C dan \`grading_config.correct\` kode benar.
   - MC multi: sama, dan pilih semua kode benar.
   - Benar/Salah: \`correct\` = 1 atau 0.
   - Matching: kode pernyataan, kode pilihan, dan \`pairs\` kode→kode.
   - Short Answer: masukkan daftar jawaban yang diterima; aktifkan opsi case-sensitive bila perlu.
   - Numerical: nilai benar dan toleransi absolut, misalnya 9.8 ± 0.2.
   - Essay: hanya teks soal dan bobot; jawaban dinilai manual.
2. Buat **Paket Tes Online** (tidak langsung published), pilih institusi, waktu, durasi,
   maksimal percobaan dan skor minimum.
3. Buka **Isi Paket Tes** dan tambahkan soal bank dari institusi yang sama.
4. Terbitkan paket setelah setidaknya satu soal siap.
5. Buka **Peserta Tes Online** dan tugaskan akun pendaftar yang aktif.
6. Setelah peserta mengumpulkan, buka **Hasil Tes Online**. Nilai objektif dihitung
   otomatis, sedangkan esai harus direview sebelum hasil akhir terbit.

## Keamanan dan batasan

- Tenant boundary: soal, paket, penugasan dan review dibatasi unit operasional.
  Reviewer lintas-unit ditolak.
- Hanya peserta pada assignment aktif dapat memulai atau menyimpan jawaban.
  Mencoba ID milik peserta lain harus menghasilkan 403/404.
- \`grading_snapshot\` tidak dipaparkan pada halaman Livewire peserta.
- Timeout server mengunci jawaban; submit ulang aman (idempotent). Finalisasi kedaluwarsa tetap dicatat meskipun percobaan berikutnya ditolak.
- Pilihan menjodohkan yang dikosongkan diperlakukan sebagai belum dijawab; nilai numerik/toleransi tak hingga ditolak.
- Data baru hanya disimpan pada tabel \`online_exam_*\`.
- Tidak ada integrasi Moodle, pengawas video, anti-cheat otomatis, import XLSX,
  Cloze, kalkulasi dinamis, maupun drag-and-drop pada fase ini.
- Soal matching: kode huruf/angka/underscore (maks. 30 karakter).
- Perubahan hasil objektif setelah ujian final tidak tersedia.
- Tunda implementasi produksi sampai UAT pada browser desktop/mobile selesai.

## Audit regresi existing

| Jalur existing | Perubahan kode pada modul tersebut | Validasi |
| --- | --- | --- |
| \`TestBookingService\` | Tidak ada | CI existing + UAT staf |
| \`AdmissionTestResult\` & aksi hasil tes | Tidak ada | CI existing + UAT |
| \`UnitConfigurationService\`, pinned form | Tidak ada | CI existing + UAT |
| Modul Sertifikasi/Pelatihan | Tidak ada | CI existing |
| Dashboard, pendaftaran, pembayaran, email, VA | Tidak ada | CI existing + smoke staging |
| \`AdminPanelProvider\` | Tambah grup navigasi **Tes Online** saja | Smoke navigation |

## Penyelesaian operasional ujian

- Paket hanya dapat diterbitkan jika memuat setidaknya satu soal aktif dari unit yang sama.
- Peserta yang sudah memulai ujian dapat melanjutkan attempt aktif meskipun jendela pembukaan
  paket telah berakhir. Percobaan baru tetap dilarang setelah paket ditutup.
  Menonaktifkan penugasan atau unit tetap memblokir akses.
- Halaman pengerjaan memeriksa masa berlaku server setiap 15 detik ketika browser terbuka.
  Autosave tetap menjadi sumber jawaban terakhir sebelum tenggat.
- Scheduler menjalankan `spmb:finalize-online-exams` setiap menit untuk memfinalisasi
  attempt kedaluwarsa tanpa membutuhkan browser peserta. Command ini memakai transaksi,
  lock, dan pemeriksaan status agar proses ulang tidak menghasilkan penilaian ganda.
- Jalankan `php artisan schedule:run` melalui cron satu kali tiap menit di server
  sesuai standar deployment Laravel. Supervisor queue worker **tidak** menggantikan scheduler.
  Jika memakai `schedule:work`, pastikan tidak ada dua scheduler independen yang tidak
  dimaksudkan; finalisasi tetap idempotent.
- Validasi operasional: `php artisan schedule:list`, dan
  `php artisan spmb:finalize-online-exams --limit=500`.
  Command aman jika tabel ujian belum dimigrasikan; gunakan `--limit` 1–5000.
- Pada edit soal, kunci jawaban dipulihkan hanya di formulir admin terotorisasi,
  tidak pada serialisasi model peserta.
- CI menguji finalisasi ulang, jendela tutup, pencabutan akses, publikasi soal aktif,
  serta autosave dan submit via Livewire. Bukti staging/browser tetap diperlukan.

## Integritas paket dan penugasan

- Paket yang **sudah diterbitkan tidak dapat ditambah atau dikurangi soal**.
  Batalkan publikasi dahulu untuk menyunting komposisi paket. Penghapusan item
  ditolak setelah paket memiliki attempt, demi mempertahankan audit riwayat.
- Menonaktifkan soal yang masih digunakan paket terbit juga ditolak;
  terbitkan kembali hanya bila **seluruh** item menunjuk soal aktif dari unit
  paket yang sama. Ujian menolak mulai jika daftar soal menjadi tidak lengkap,
  bukan diam-diam melewatkan soal.
- Membuat penugasan duplikat tidak menghidupkan lagi akses yang sebelumnya
  dicabut. Gunakan aksi **Aktifkan Akses** secara eksplisit; aksi aktivasi dan
  pencabutan mencatat audit trail. Pembuatan penugasan baru juga diaudit.
- Tes regresi untuk penambahan/penghapusan soal terbit, penonaktifan soal,
  paket campuran aktif/nonaktif, dan penugasan ulang yang dicabut.

## Staging release gates

- [ ] Backup database dan file pendaftar, lalu uji restore terisolasi.
- [ ] Jalankan \`php artisan migrate --force\` di staging dengan salinan database aman.
- [ ] Admin Unit A tidak dapat melihat/mengedit soal, penugasan, hasil Unit B.
- [ ] Peserta A tidak dapat melihat attempt Peserta B.
- [ ] Edit bank soal setelah attempt dimulai tidak mengubah jawaban benar snapshot.
- [ ] Soal objektif/essay, autosave, time-out, submit ulang, review diuji dengan data sintetik.
- [ ] Uji dua tab dan klik submit ganda dengan koneksi lambat.
- [ ] Uji smartphone, keyboard, dan tampilan gelap (dark mode).
- [ ] Uji alur pendaftaran lama, VA, sertifikat, jadwal booking dan laporan tetap berjalan.
- [ ] Catat build SHA, bukti hasil, operator, waktu, keputusan GO/NO-GO.

**Operator wajib menandai hasil aktual**; checklist ini bukan bukti uji staging sudah dilakukan.
