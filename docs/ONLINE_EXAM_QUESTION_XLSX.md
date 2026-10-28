# Tes Online — Impor/Ekspor Bank Soal XLSX

## Operasional administrator

Pada **Admin → Tes Online → Bank Soal Online** tersedia tiga aksi:
- **Template Soal XLSX**: pilih unit; template berisi tujuh contoh tipe soal dan petunjuk pada worksheet kedua.
- **Impor Bank Soal XLSX**: pilih unit; unggah file .xlsx privat maksimal 5 MB dan 500 soal. Semua baris diperiksa lebih dulu. Jika ada satu kegagalan, transaksi dibatalkan dan tidak ada soal baru yang disimpan.
- **Ekspor Bank Soal XLSX**: pilih unit untuk mengunduh daftar soal beserta opsi dan **kunci jawaban**. File ini bersifat rahasia; hanya Super Admin atau Admin Unit yang berwenang untuk unit terkait yang boleh mengunduh.

## Header dan format

Worksheet pertama wajib bernama bebas tetapi header baris pertama wajib persis:

\`type | prompt | category_code | difficulty | competency_tag | points | is_active | choices_json | prompts_json | grading_json\`

- \`type\`: single_choice, multiple_choice, true_false, matching, short_answer, numerical, essay.
- \`category_code\`: kode kategori aktif di unit tersebut; boleh kosong.
- \`difficulty\`: easy, medium, hard.
- \`points\`: desimal positif dengan maksimal dua tempat desimal (0.01–10000).
- \`is_active\`: 1 atau 0.
- Kolom *json* memakai JSON valid, contoh \`{"A":"Newton","B":"Joule"}\`, \`{}\`, \`{"correct":"A"}\`. Gunakan template sebagai referensi tiap jenis.
- \`competency_tag\` boleh kosong.

Import hanya **create** (append-only), bukan update atau sinkronisasi; soal existing tidak berubah. Pertanyaan dengan tipe/kategori/teks sama yang telah terdaftar ditolak untuk mencegah impor ulang. Tidak ada pemetaan nomor registrasi, keputusan seleksi, maupun perubahan workflow dalam fitur ini.

File diunggah ke disk \`local\` privat dan dihapus setelah parsing (kecuali request ditolak sebelum membaca file, yang masih bisa diproses ulang oleh Filament). Ekspor dibuat sebagai file temporer yang dihapus setelah dikirim ke browser. Catatan audit menyimpan jumlah soal yang diimpor, **bukan kunci jawaban**.

## Release gates

- [ ] CI SQLite lengkap + MySQL (termasuk 7 tipe dan regresi Fase 1/2).
- [ ] UAT staging: unduh template, impor 500 soal, tangani file gagal, ekspor dan baca ulang hasil.
- [ ] Uji penolakan export/import silang unit dan tampilan mobile.
- [ ] Uji backup/restore, kebijakan retensi berkas, dan distribusi file kunci jawaban internal.
- [ ] Verifikasi tidak ada perubahan pada alur pendaftaran dan seleksi SPMB/PMB.

Belum termasuk pengelolaan lampiran/gambar, import format Moodle, maupun pembaruan bank soal via XLSX. Semua memerlukan desain migrasi versi soal terpisah.
