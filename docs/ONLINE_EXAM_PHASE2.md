# Tes Online Fase 2 — Bank Soal dan Blueprint

Status: iterasi awal pada feature branch. Belum terverifikasi untuk produksi.

## Fitur yang diimplementasikan

- Kategori bank soal per unit: kode, nama, status aktif.
- Metadata soal: kategori opsional, kesulitan mudah/sedang/sulit, tag kompetensi.
- Paket: prodi opsional untuk unit yang sama, blueprint jumlah soal per kategori dan kesulitan.
- Saat peserta mulai, sistem mengambil soal aktif secara acak dari unit dan kategori sesuai blueprint; isi dan kunci disimpan pada snapshot attempt.
- Paket tanpa blueprint tetap menggunakan Isi Paket Tes manual Fase 1. Kedua mode tidak dapat dicampur.
- Penugasan masih manual. Tidak ada perubahan terhadap hasil seleksi atau tahap pendaftaran.

## Verifikasi staging

1. Backup dan restore database terisolasi sebelum migrasi additive.
2. Jalankan migrasi baru.
3. Buat kategori/soal dan paket blueprint, lalu tes dua peserta dengan pilihan acak.
4. Uji kuota kurang, kategori unit lain, perubahan kategori saat terbit, dan penyimpanan/submit.
5. Uji manual Fase 1, scheduler dan isolasi unit.
6. Uji desktop/mobile, backup/recovery dan pantau performa ujian serentak.

Belum mencakup import XLSX, penugasan otomatis dari prodi/gelombang, gambar soal, analitik maupun pengawas online.
