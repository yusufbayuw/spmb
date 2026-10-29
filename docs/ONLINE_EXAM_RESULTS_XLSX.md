# Laporan Hasil Tes Online (CBT) mandiri — XLSX

## Penggunaan

1. Login sebagai Admin Unit atau Super Admin.
2. Buka menu Tes Online → Paket Tes Online.
3. Pada paket yang diizinkan, klik "Unduh Hasil XLSX" dan konfirmasikan.
4. File memiliki tiga sheet:
   - Ringkasan Peserta: setiap akun yang ditugaskan, termasuk yang belum mulai,
     akses dicabut, jumlah percobaan, status serta nilai percobaan terakhir.
   - Riwayat Percobaan: seluruh percobaan dengan waktu server, poin dan persentase
     yang hanya muncul ketika status telah "graded".
   - Petunjuk: paket/unit, waktu ekspor, jumlah data dan informasi keamanan.

## Batasan dan keamanan

- CBT adalah **fasilitas tambahan**, bukan tahap wajib SPMB. Ekspor tidak membuat
  tes penerimaan wajib, tidak mengubah tahap/keputusan seleksi, dan tidak menulis
  tabel hasil tes penerimaan.
- Laporan juga dapat dibuat dari paket mandiri tanpa satu pun registrasi SPMB.
- Setiap file hanya memuat satu paket CBT. Admin Unit tidak dapat mengekspor
  paket unit lain; Super Admin dapat mengakses paket unit operasional.
  Akun pendaftar tidak berhak mengunduh laporan.
- Tidak memuat soal, jawaban peserta, kunci jawaban, rekaman, atau data registrasi.
  Nama dan email peserta merupakan informasi rahasia; jangan bagikan ke publik.
- Nilai nol berbeda dari nilai kosong: kolom kosong berarti belum dinilai final.
- Status "in_progress" yang melewati tenggat dapat sedang menunggu scheduler.
- Seluruh percobaan muncul pada sheet riwayat, bukan hanya skor terakhir/tertinggi.
- File dibuat di storage lokal privat dengan nama acak, disajikan dengan respons
  "private, no-store", dan dihapus setelah berhasil dikirim sebagai unduhan.
- Setiap ekspor diaudit beserta operator, unit, jumlah penugasan dan percobaan.
- Nama/email dengan awalan menyerupai formula spreadsheet diamankan sebagai teks.
- Berkas pelaporan ini bersifat read-only dan tidak boleh diimpor untuk keputusan
  seleksi atau sebagai data peserta yang sudah diverifikasi.

## Checklist staging

- [ ] CI full Laravel dan MySQL hijau untuk commit rilis.
- [ ] UAT unduhan pada browser dan spreadsheet viewer yang didukung.
- [ ] Uji beban ribuan peserta dan kapasitas disk sementara; pastikan cleanup jika
      koneksi terputus atau proses worker gagal.
- [ ] Uji peran Admin Unit, Super Admin dan peserta, termasuk akses lintas unit.
- [ ] Validasi file tidak berisi soal, jawaban atau kunci dan audit tampil.
- [ ] Uji backup/restore serta rollback sesuai SOP deployment.

Lulus CI otomatis tidak sama dengan telah diterapkan dan diuji di production.


## Pengamanan operasional unduhan

Unduhan biasa menggunakan delete-after-send. Jika koneksi browser terputus
sebelum respons selesai, file sementara mungkin tidak terhapus. Karena itu
scheduler menjalankan pembersihan **sekali setiap hari pukul 02.30 waktu
aplikasi**, dengan batas 500 file lama per eksekusi:

```bash
php artisan schedule:list
php artisan spmb:prune-online-exam-reports --hours=24 --limit=500 --dry-run --json
php artisan spmb:prune-online-exam-reports --hours=24 --limit=500 --json
```

- Minimal umur 24 jam sebelum sebuah file dianggap kedaluwarsa.
- Hanya file `exports/online-exam-results/{uuid}.xlsx` pada disk `local`
  yang boleh dihapus; file bank soal, upload peserta, dan file bernama manual
  di lokasi lain tidak disentuh.
- `--dry-run` memeriksa kandidat tanpa menghapus. `--limit` membatasi
  pekerjaan jika server mempunyai backlog ekspor.
- Output hanya jumlah berkas, tidak mencantumkan identitas peserta.
- Periksa permission direktori storage dan keberjalanan Laravel scheduler
  pada server nyata. Command aman dijalankan berulang kali.
- Pemeriksaan pra-rilis `spmb:release:preflight` juga menolak konfigurasi
  disk ekspor CBT yang berada di bawah root public/web.

## Validasi otomatis pada CI

- `OnlineExamReportUiTest`: menguji aksi download melalui Filament sebagai
  Admin Unit, membatasi unit, serta memastikan tidak menulis tabel seleksi.
  Ini **bukan** browser real.
- `OnlineExamExportPruneTest`: menguji dry-run, batas jumlah, usia berkas,
  dan proteksi berkas non-CBT.
- `OnlineExamResultSpreadsheetLoadTest`: hanya dapat berjalan pada database
  **MySQL CI terisolasi bernama `spmb_test`**, setelah diaktifkan eksplisit
  oleh `SPMB_CBT_LOAD_TEST=1`. Menyiapkan 1.200 akun dan 2.400 attempt
  sintetis, menghitung jumlah baris di workbook akhir, memastikan skor belum
  final tidak bocor, dan melaporkan durasi/memori/ukuran XLSX. CI menyimpan
  `cbt-report-load-synthetic.json` sebagai artefak.
- Tidak ada beban sintetis yang boleh dijalankan di database staging yang
  berisi data pengguna asli atau database produksi.

## Gerbang staging/production yang belum otomatis

- [ ] Verifikasi backup database dan storage privat, **uji restore** di salinan terisolasi.
- [ ] Jalankan preflight *read-only* pada staging dengan profil staging;
      tidak boleh ada kegagalan, dan telaah seluruh peringatan.
- [ ] Browser UAT: login admin unit, unduh, buka XLSX di Excel dan LibreOffice,
      pastikan nama/email/riwayat tepat dan tidak ada kunci soal.
- [ ] Coba akun dari unit berbeda dan pendaftar: unduhan harus ditolak.
- [ ] Simulasikan unduhan terputus dan verifikasi file lama akhirnya dipruning
      tanpa menyentuh ekspor bank soal/berkas pendaftar.
- [ ] Ukur beban beberapa unduhan paralel di staging menggunakan **data sintetik**;
      tentukan ambang latensi, kapasitas disk sementara, dan memori yang aman.
- [ ] Pastikan cron/scheduler berjalan, private disk writable, audit terlihat,
      serta tidak ada URL publik untuk file report.
- [ ] Catat commit SHA, operator, bukti UAT, persetujuan rilis, dan
      prosedur rollback deployment; produksi hanya setelah GO.

Workflow CI memakai database dan layanan **sintetis/terisolasi**. Hasil hijau
tidak membuktikan staging asli, backup/restore, atau deployment production.
