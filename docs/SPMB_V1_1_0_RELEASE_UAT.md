# SPMB v1.1.0 — Staging UAT & Release Checklist

> Dokumen ini **bukan bukti UAT sudah dijalankan**. Isi status dengan bukti dari server staging sebenarnya. 
> **NO-GO** jika backup/restore atau salah satu pemeriksaan wajib belum lulus.

## Ruang lingkup

- PR: https://github.com/yusufbayuw/spmb/pull/47
- Versi stabil kode: `release/v1.0.0`.
- Fitur baru: formulir cetak dinamis, email manual HTML, pengiriman akses LMS dari Hasil Tes, tes multi-prodi, panduan verifikasi email.
- Hanya source dari commit SHA yang telah direview dan CI hijau yang boleh digunakan untuk staging atau production.
- `main` tidak otomatis mendeploy aplikasi.

## Gate yang wajib dicatat operator

| Gate | Ekspektasi | Status awal |
| --- | --- | --- |
| Backup dan restore off-host | Database serta file privat dapat dipulihkan secara terisolasi | NOT TESTED |
| Staging terisolasi | Database, alamat tujuan email, kunci aplikasi, dan media terpisah dari production | NOT TESTED |
| Migrasi baru | Dua migrasi bertanggal 2026-10-27 berhasil dan tidak menghapus data existing | NOT TESTED |
| Kecocokan lingkungan | PHP 8.3+, PHP CLI/worker kompatibel, HTTPS, aset `public/build` tersedia | NOT TESTED |
| Hak akses staf | TU/Admin Unit hanya mencetak dan mengirim email untuk unit sendiri | NOT TESTED |
| Formulir dan data historis | Versi konfigurasi yang pinned, data orang tua, rapor, prestasi, PDF melalui print | NOT TESTED |
| Tes multi-prodi | Tes global, tes satu/beberapa prodi, jalur, hasil lama dan booking sesuai | NOT TESTED |
| Panduan verifikasi | Instruksi Inbox, Spam/Junk, Promosi, Sosial dan resend tampak pada portal Filament pendaftar | NOT TESTED |
| Pengiriman email HTML | Bold, italic, daftar, dan tautan tampil benar di Gmail maupun Outlook | NOT TESTED |
| Pengiriman LMS | Hanya email terverifikasi; akun LMS sudah dibuat eksternal; password hanya bila mutlak diperlukan | NOT TESTED |
| Kredensial sementara | Body terenkripsi saat antre; setelah sent/failed/skipped dihapus; job kedaluwarsa tidak dikirim | NOT TESTED |
| Queue/SMTP | Supervisor melayani `emails,notifications,default`; email uji eksternal sampai di kotak masuk | NOT TESTED |
| Audit produksi | Preflight dan dashboard Audit Siap Production tanpa peringatan/FAIL | NOT TESTED |
| Sign-off | Operator staging, pemilik aplikasi, dan pimpinan rilis menyetujui hasil | NOT TESTED |

Gunakan status PASS, FAIL, BLOCKED atau NOT TESTED. Bukti tiap PASS wajib memiliki lokasi dokumentasi yang tidak mengekspos identitas atau password asli peserta, operator, waktu dan SHA yang diuji.

## Langkah staging

1. Siapkan akun pendaftar **sintetis** dan mailbox sandbox; jangan gunakan alamat peserta production.
2. Backup database dan `storage` privat, simpan di luar host. Uji restore sebelum migrasi.
3. Deploy satu commit SHA rilis lengkap dengan `public/build`; jangan timpa `.env`, `APP_KEY`, file upload, atau media production.
4. Berhentikan worker staging selama pergantian kode/migrasi, lalu:

```bash
php -v
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate:status
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
php artisan spmb:release:preflight --profile=staging --json
php artisan queue:failed
php artisan schedule:list
```

5. Nyalakan kembali worker dan scheduler staging, lalu jalankan seluruh UAT pada matriks di atas.
6. Tandai kegagalan apa adanya; jangan mengubah status gate menjadi PASS berdasarkan CI saja.
7. Setelah seluruh gate lulus dan human sign-off, merge PR ke `main`, tandai SHA rilis dan deploy production melalui maintenance window.

## Pengamanan wajib

- Kedua migrasi bersifat additive: tabel `registration_manual_emails` dan JSON nullable `admission_tests.study_program_ids`. Wajib backup lebih dulu.
- Jangan jalankan `migrate:fresh`, `key:generate`, atau demo seeder di production.
- `APP_KEY` harus dipertahankan agar data yang sudah dienkripsi tetap dapat dibaca.
- Pastikan SMTP production, sender domain SPF/DKIM/DMARC dan queue `emails` sudah aktif.
- Isi kredensial Moodle/LMS harus dibuat di sistem LMS sendiri; fitur SPMB tidak membuat akun eksternal.
- **Utamakan tautan aktivasi atau reset password**. Jika password sementara terpaksa dikirim, mailbox harus terverifikasi dan password wajib diganti.
- Unduh PDF langsung dari server **belum tersedia**; gunakan cetak/simpan PDF dari browser.
- Jika migrasi sudah diterapkan, rollback tidak cukup hanya mengganti branch kode; gunakan backup database yang konsisten jika perlu.
