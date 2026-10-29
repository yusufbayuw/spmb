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
