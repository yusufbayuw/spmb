# SPMB

Platform penerimaan peserta didik dan mahasiswa berbasis Laravel dan Filament.

Repository ini bersifat **white-label**. Identitas institusi, nama portal, kontak, alamat, logo, unit pendidikan, program studi, dan data operasional tidak boleh di-hard-code ke source code. Setiap deployment mengatur identitasnya melalui environment/configuration dan data aplikasi.

## Navigasi dokumentasi

[Instalasi lokal](#instalasi-lokal) · [Deployment production](#production-deployment) · [Queue dan scheduler](#queue-dan-scheduler) · [Operasional email](#notification-delivery-manager) · [Profil staf](#profil-dan-keamanan-akun) · [Lifecycle data](#data-lifecycle-dan-bulk-action-status-implementasi) · [Testing](#testing)

## Cakupan

SPMB mendukung tiga profil operasional:

- `K12` — pendidikan anak usia dini dan sekolah.
- `HIGHER_EDUCATION` — perguruan tinggi.
- `MIXED` — keduanya dalam satu instalasi.

Role utama:

- `super_admin`
- `admin_unit`
- `tu`
- `pendaftar`

Aplikasi menggunakan satu autentikasi utama pada `/login`, kemudian mengarahkan user berdasarkan role:

- staf → `/admin` (profil mandiri: `/admin/profile`);
- pendaftar → `/pendaftar` (profil mandiri: `/pendaftar/profile`).

Rute lama `/profile` mengarahkan pengguna ke profil panel yang sesuai. Kewenangan tetap mengikuti role, unit, serta status aktif akun.

## Fitur Utama

### Pendaftaran

- pembukaan pendaftaran per unit;
- jalur pendaftaran;
- dukungan program studi untuk perguruan tinggi;
- formulir identitas dan data orang tua/wali;
- custom field;
- dokumen pendaftaran;
- pembayaran dan Virtual Account;
- nomor pendaftaran yang dapat dikonfigurasi;
- tes dan penjadwalan;
- seleksi dan publikasi hasil;
- penawaran penerimaan;
- daftar ulang;
- workflow yang dapat dikonfigurasi per unit/program.

### Operasional

- pemisahan akses Super Admin, Admin Unit, TU, dan Pendaftar;
- dashboard dan resource Filament;
- database notification;
- PWA dan Web Push;
- audit log;
- export Excel;
- dokumen/printable;
- pengaturan informasi publik dan helpdesk;
- account consent serta consent proses pendaftaran;
- mode K12, perguruan tinggi, dan mixed;
- profil staf dan perubahan password mandiri yang aman;
- pengiriman ulang email dan pemulihan akses pendaftar;
- pengingat tindakan tertunda dan koreksi email yang harus dikonfirmasi pendaftar;
- monitoring pengiriman, pencatatan retry, serta penghapusan data yang terkendali.

## Teknologi

- PHP 8.3+
- Laravel 12
- Filament 3
- Livewire
- Spatie Laravel Permission
- Redis untuk cache/queue pada deployment yang mendukungnya
- Laravel Queue
- Web Push / VAPID
- Vite

## Instalasi Lokal

```bash
git clone <repository-url>
cd spmb

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Konfigurasikan database pada `.env`, lalu:

```bash
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Pada environment `local` dan `testing`, `DatabaseSeeder` juga memanggil demo seeder sehingga aplikasi dapat langsung digunakan untuk pengembangan dan pengujian.

## Konfigurasi Identitas Deployment

Identitas deployment tidak disimpan sebagai nama institusi tertentu di repository.

Contoh konfigurasi:

```env
APP_NAME="SPMB"

SPMB_PORTAL_NAME="Portal Penerimaan"
SPMB_FOUNDATION_NAME="Institusi Pendidikan"
SPMB_FOUNDATION_WEBSITE=
SPMB_FOUNDATION_EMAIL=
SPMB_FOUNDATION_PHONE=
SPMB_FOUNDATION_WHATSAPP=
SPMB_FOUNDATION_ADDRESS=
SPMB_FOUNDATION_SERVICE_HOURS=
SPMB_PORTAL_LOGO_PATH=
```

Untuk deployment riil, isi nilai tersebut sesuai institusi masing-masing.

Nama portal pada panel Filament, login, email, dan bagian UI yang relevan mengambil nilai dari konfigurasi, bukan nama institusi yang ditulis langsung di source code.

## Mode Operasional

```env
SPMB_MODE_OPS=MIXED
```

Nilai yang didukung:

```text
K12
HIGHER_EDUCATION
MIXED
```

Mode hanya mengatur data mana yang aktif/terlihat. Perubahan mode tidak menghapus data existing.

## Seeder

Seeder dibagi menjadi dua kelompok.

### Production Reference Seeder

Aman untuk menyiapkan reference/authorization data:

```bash
php artisan db:seed --class=ProductionReferenceSeeder
```

Seeder ini menyiapkan data struktural seperti jenjang pendidikan serta role/permission. Seeder ini tidak membuat unit, program studi, akun demo, pembukaan pendaftaran, kuota, sesi tes, atau data transaksi.

### Demo Seeder

Demo seeder digunakan untuk development/testing:

```bash
php artisan db:seed --class=DemoSeeder
```

Demo seeder hanya berjalan pada environment `local` atau `testing`. Pada production, demo seeder berhenti tanpa membuat atau mengubah data operasional.

Fresh development:

```bash
php artisan migrate:fresh --seed
```

Akun demo memakai domain `example.test` dan password demo hanya untuk local/testing.

**Jangan menggunakan akun demo pada production.**

## Production Deployment

Deployment existing cukup melakukan upgrade schema/code tanpa menghapus data:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

**Migrasi baru untuk fitur komunikasi** (dijalankan otomatis oleh `migrate --force`):

- `2026_10_10_130000_create_mail_delivery_attempts_table.php` — riwayat pengiriman email;
- `2026_10_10_180000_extend_mail_delivery_tracking.php` — jumlah dan waktu percobaan job;
- `2026_10_10_181000_create_pending_applicant_email_changes.php` — permintaan koreksi email yang menunggu persetujuan.

Sebelum upgrade, lakukan backup database dan file privat. Jalankan **migrasi sebelum menyalakan worker versi baru**, pastikan `APP_URL` menunjukkan alamat publik yang benar (untuk tautan signed), dan konfigurasi `MAIL_*` menggunakan SMTP/transport yang dapat mengirim. Verifikasi worker dan scheduler sebelum melakukan UAT end-to-end.

**Merge GitHub tidak otomatis melakukan deployment ke server.**

Jangan menjalankan:

```bash
php artisan migrate:fresh
```

pada database production.

Menjalankan `php artisan db:seed --force` pada production hanya menjalankan reference seeder melalui `DatabaseSeeder`; demo data tidak dibuat.

## Queue dan Scheduler

Contoh worker (sesuaikan path binary PHP dan driver queue dengan deployment):

```bash
php artisan queue:work --queue=emails,notifications,default --sleep=3 --tries=5 --timeout=120
```

Email SPMB berjalan di queue `emails` (job maksimal 5 percobaan dengan jeda bertahap). Notifikasi database/Web Push menggunakan queue `notifications`. Jalankan worker di bawah Supervisor/systemd atau process manager lain dan pastikan **semua nama queue di atas benar-benar dikonsumsi**. Dispatch berhasil belum berarti email diterima pengguna.

Diagnostik:

```bash
php artisan queue:failed
php artisan schedule:list
```

Laravel Scheduler juga harus dijalankan **setiap menit** oleh cron (contoh; gunakan pengguna aplikasi):

```cron
* * * * * cd /path/to/spmb && /path/to/php artisan schedule:run >> /dev/null 2>&1
```

Jangan memasang scheduler ganda. Scheduler juga menangani tugas lain, termasuk pemeriksaan penawaran penerimaan kedaluwarsa.

### Pengingat otomatis: opt-in

Fitur pengingat otomatis **nonaktif secara default**, sehingga deployment tidak tiba-tiba mengirim pesan massal. Untuk mengaktifkan setelah pengujian:

```dotenv
SPMB_AUTOMATIC_REMINDERS_ENABLED=true
SPMB_REMINDER_INTERVAL_HOURS=48
```

Perbarui cache konfigurasi (`php artisan config:cache`) dan periksa `php artisan schedule:list`. Jika aktif, `spmb:remind-pending-actions --execute` berjalan setiap hari pada **09.00 menurut timezone aplikasi** (default `Asia/Jakarta`). Pengingat untuk jenis yang sama pada pendaftaran yang sama dibatasi interval minimal, default 48 jam; job mengecek ulang syarat pengiriman sebelum mengirim.

```bash
# Pratinjau (tanpa mengirim), hanya ketika fitur diaktifkan:
php artisan spmb:remind-pending-actions

# Mengantrekan email nyata; gunakan hanya setelah pemeriksaan:
php artisan spmb:remind-pending-actions --execute
```

Jika `SPMB_AUTOMATIC_REMINDERS_ENABLED=false`, kedua perintah tidak mengirim email. **Pengiriman manual** di halaman Pendaftaran tetap berfungsi tanpa mengaktifkan scheduler pengingat.

## Notification Delivery Manager

[PR #18](https://github.com/yusufbayuw/spmb/pull/18) memperkenalkan pengiriman ulang; [PR #20](https://github.com/yusufbayuw/spmb/pull/20) melengkapinya dengan pemulihan akun, pengingat tindakan tertunda, koreksi email, dan monitoring. Prinsip utama: **memulihkan komunikasi tanpa mem-bypass workflow pendaftaran, pembayaran, atau seleksi**.

### Menu Admin

| Lokasi | Fungsi |
| --- | --- |
| **Pendaftaran → Kirim Ulang Email** (aksi pada baris) | Pilih jenis email yang masih relevan, masukkan alasan, lalu antrekan |
| **Pendaftaran → Riwayat Email** | Periksa hingga 20 catatan terbaru untuk satu pendaftaran |
| **Pendaftaran → Koreksi Email Akun** | Ajukan alamat email baru setelah verifikasi identitas; tunggu persetujuan pemilik akun |
| **Sistem & Akses → Pengiriman Email** | Monitoring dan filter lintas pendaftar berdasarkan status/jenis |
| **Sistem & Akses → Pemulihan Akun** | Bantuan verifikasi/reset akun pendaftar yang belum membuat pendaftaran |

Rute monitoring: `/admin/notification-delivery-center`; rute bantuan pra-pendaftaran: `/admin/applicant-account-recovery`. Untuk peserta yang **sudah mempunyai pendaftaran**, gunakan aksi di menu **Pendaftaran**.

### Jenis pengiriman ulang

| Jenis email | Syarat atau perilaku |
| --- | --- |
| Verifikasi akun | Akun aktif, email belum diverifikasi; tautan signed baru dan memiliki kedaluwarsa |
| Informasi Virtual Account | Masih tahap pembayaran, VA existing, pembayaran berstatus relevan; **tidak menciptakan VA/Payment baru** |
| Pengumuman | Pengumuman berstatus `published`; keputusan/hasil bisnis tidak diterbitkan ulang |
| Reset password | Menggunakan mekanisme broker Laravel, tanpa memperlihatkan token/password kepada petugas |
| Pengingat revisi | Data masih harus diperbaiki atau berkas ditolak pada tahap yang sesuai |
| Pengingat bukti pembayaran | Masih tahap pembayaran, bukti transfer belum diunggah |
| Pengingat tahapan tes | Masih tahap tes; peserta diminta mengecek jadwal/konfirmasi pada portal |
| Pengingat penawaran | Penawaran `offered` dan belum kedaluwarsa |

Pilihan jenis email muncul menurut **kondisi terkini**, bukan semua opsi untuk semua pendaftaran. Worker memeriksa ulang penerima dan status proses saat job berjalan. Pengiriman email ulang tidak membuat nomor VA, pembayaran, atau keputusan seleksi baru. Verifikasi pembayaran tetap **manual berdasarkan bukti transfer**.

### Hak akses

- **Super Admin** dapat menangani semua unit dan bantuan akun pra-pendaftaran yang belum ditetapkan ke unit.
- **Admin Unit** hanya dapat menangani pendaftar pada unit sendiri. Akun pra-pendaftaran harus sudah memiliki `unit_id` yang sama; jika tidak ada, hanya Super Admin yang dapat membantu.
- **TU** tidak dapat memakai aksi kirim ulang, koreksi email, pemulihan akun, ataupun dashboard monitoring ini.
- **Pendaftar** tetap dapat mengelola akunnya di portal; staf tidak melihat/mengambil alih token, tidak menandai verifikasi akun secara paksa, dan tidak bisa melompati tahap pendaftaran.

Jangan memberikan hak akses resource **Pengguna** secara luas hanya demi pemulihan akun. Admin Unit memakai halaman bantuan khusus yang memeriksa kepemilikan unit pada server.

### Koreksi email dengan persetujuan pemilik akun

1. Petugas membuka **Pendaftaran → Koreksi Email Akun**, memverifikasi identitas terhadap **NIK dan tanggal lahir** pendaftaran, mengisi email baru serta alasan.
2. Server membuat permintaan `pending`, mencatat audit, lalu mengantrekan tautan **bertanda tangan** ke email **baru**, berlaku **30 menit**. Email akun yang lama **belum berubah**.
3. Pemilik akun harus **login sebagai pendaftar** dan membuka tautan tersebut. Server mengecek akun, kedaluwarsa, status permintaan, email lama, serta keunikan email baru sebelum memperbarui alamat.
4. Setelah disetujui, email baru ditandai terverifikasi. Tautan lama/yang digantikan tidak dapat digunakan kembali. Akun yang terhubung lintas unit membutuhkan Super Admin untuk mengajukan perubahan.

Pemeriksaan NIK dan tanggal lahir pada aplikasi **bukan pengganti prosedur verifikasi identitas institusi**. Petugas tetap harus mengikuti SOP dan tidak menuliskan NIK/token ke catatan alasan maupun tiket bantuan.

### Status, batas frekuensi, dan monitoring

Tabel `mail_delivery_attempts` mencatat jenis, peminta, penerima, asal otomatis/manual, alasan, status, jumlah percobaan job (`attempt_count`), serta waktu. Status yang ditampilkan:

| Status | Arti |
| --- | --- |
| `queued` | Pengiriman dalam antrean |
| `sent` | Pesan **diserahkan ke server email**, bukan kepastian terkirim ke inbox atau dibaca |
| `failed` | Proses gagal setelah retry atau dispatch gagal |
| `skipped` | Dilewati karena penerima/kondisi bisnis sudah berubah |

Untuk pengiriman manual: alasan wajib (maksimum **500 karakter**), cooldown **2 menit** per jenis, maksimal **5 kali per jenis/per pendaftaran dalam 24 jam**, serta perlindungan ketika email masih antre selama **15 menit**. Permintaan bantuan akun sebelum pendaftaran juga memiliki pembatasan frekuensi.

Dashboard menampilkan jumlah antrean, antrean **lebih dari 15 menit**, gagal, dan status diserahkan ke email server selama 24 jam terakhir. Riwayat dipaginasi (25 catatan/halaman), dengan filter jenis/status dan **pembatasan per unit**. Jumlah `attempt_count` adalah usaha menjalankan job, **bukan** jumlah pembukaan email atau bukti penerimaan pengguna.

Jika email dilaporkan tidak diterima: cek riwayat dan status, lalu konfigurasi `MAIL_*`, worker `emails`, antrean gagal, alamat penerima, dan log provider email. Jangan mengirim ulang saat antrean lama masih aktif. Status `sent` tidak dapat digunakan sebagai bukti pesan sudah sampai di inbox.

## PWA dan Web Push

PWA menggunakan service worker untuk installability dan push notification. Halaman authenticated tidak disimpan ke cache offline.

Generate VAPID key satu kali pada deployment:

```bash
php artisan webpush:vapid
```

Kemudian simpan:

```env
VAPID_SUBJECT=
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
```

Jangan mengganti VAPID key pada setiap deployment karena subscription browser existing bergantung pada key tersebut.

## Session

Aplikasi memakai satu session untuk:

```text
/login
/admin
/pendaftar
/livewire
```

Karena itu cookie session selalu menggunakan path root `/`. Jangan mempersempit cookie hanya ke salah satu panel.

## White-label dari Panel Admin

Super Admin dapat mengatur identitas global melalui **Sistem & Akses → White-label Aplikasi** tanpa akses server. Nama portal, nama organisasi, logo, kontak, alamat, jam layanan, dan warna tema PWA disimpan di database.

Nilai `.env` tetap menjadi fallback untuk instalasi yang belum pernah menyimpan pengaturan white-label melalui panel. Penyimpanan pengaturan ini tidak mengubah data unit, pendaftaran, user, maupun workflow existing.

## Branding dan White-label

Source repository harus tetap netral.

Aturan:

- jangan hard-code nama institusi;
- jangan hard-code domain institusi;
- jangan menjadikan kode unit milik satu deployment sebagai business rule;
- gunakan `institution_type`, relasi database, dan configuration;
- data demo memakai identitas fiktif;
- branding production berasal dari environment/configuration dan data unit.

CI memiliki regression test yang memeriksa agar deployment-specific branding tidak kembali masuk ke source repository.

## Data Lifecycle dan Bulk Action (Status Implementasi)

SPMB memisahkan **konfigurasi** dari **data operasional**. Konfigurasi yang harus dipertahankan saat pembersihan meliputi unit, profil dan branding, program studi, jalur, pembukaan pendaftaran, tahun ajaran/gelombang, kuota, biaya, formulir, workflow, definisi dan jadwal tes, role/permission, serta pengaturan aplikasi. Akun staf juga dipertahankan.

### Bulk action Filament

- **Pendaftaran:** pilih beberapa baris melalui checkbox, kemudian **Arsipkan pendaftaran selesai**. Aksi meminta konfirmasi, memeriksa status dan otorisasi tiap record, lalu melaporkan jumlah berhasil/dilewati. Arsip bukan penghapusan permanen.
- **Pool Virtual Account:** pilih beberapa baris lalu **Batalkan VA tersedia**. Hanya VA berstatus `available`, tanpa `registration_id` dan tanpa pembayaran yang diproses. VA assigned/paid tidak dibatalkan melalui aksi ini.
- Bulk action untuk resource lain (dokumen, pembayaran, tes, seleksi, daftar ulang) **belum diimplementasikan secara menyeluruh**.

### Hapus Total terkendali (production)

Aksi **Hapus Total** untuk data yang benar-benar memenuhi syarat sudah tersedia selain reset development. Ini bukan tombol penghapusan bebas:

- **Super Admin:** dapat meminta penghapusan sepanjang seluruh pemeriksaan integritas dan keamanan lolos.
- **Admin Unit:** Super Admin terlebih dahulu harus menyalakan toggle terpisah per unit pada **Unit → Izin Hapus Total oleh Admin Unit** untuk **pendaftaran**, **VA**, atau **pembukaan/gelombang**. Semua toggle **default OFF**.
- **TU:** tidak memiliki hak Hapus Total.
- **Pendaftaran:** ditolak jika sudah terkait pembayaran, VA, tes/seleksi, pengumuman, penawaran, nomor resmi, atau tahap lanjutan lain yang dilindungi. Akun login pendaftar dan pengaturan unit tidak ikut dihapus.
- **VA:** hanya yang belum pernah ditugaskan, tidak terkait pembayaran, dan berstatus tersedia/dibatalkan.
- **Pembukaan/gelombang:** hanya yang kosong dari pendaftar dan dependensi terlarang.

UI menampilkan ringkasan dampak. Penghapusan meminta **alasan** dan konfirmasi literal **`HAPUS`**, dilindungi oleh pemeriksaan transaksi/database dan audit. Flag kewenangan **tidak mengabaikan syarat data**. Pastikan backup dapat dipulihkan sebelum operasi permanen.

### Reset development — konfigurasi dipertahankan

**Perintah ini khusus pengembangan/testing, bukan prosedur penggantian tahun ajaran atau pembersihan production.** Reset menghapus data operasional penerimaan dan akun pendaftar yang cocok, tetapi menjaga unit, profil, jalur, pembukaan pendaftaran, kuota, definisi/sesi tes, konfigurasi workflow, role/permission, akun staf, dan pool VA. Verifikasi pembayaran tetap **manual oleh Admin Unit/TU berdasarkan bukti transfer**, bukan callback bank.

**Pengaman reset:**

- Menolak `APP_ENV=production`.
- Memerlukan fingerprint koneksi database yang sama pada `SPMB_RESET_ALLOWED_TARGET` (konfigurasi server) dan argumen `--target`.
- Menolak VA berstatus `assigned`/`paid` atau masih memiliki `registration_id`. Jangan memutihkan atau mendaur ulang VA riil.
- Memerlukan konfirmasi operator interaktif dan pernyataan bahwa backup database **serta file privat** sudah diuji pemulihannya.
- Menghapus tabel operasional berdasarkan allowlist tanpa menonaktifkan foreign key; perubahan database menggunakan transaksi.
- Menghitung hash tabel konfigurasi dan snapshot akun staf, lalu membatalkan transaksi jika berubah.
- Menyimpan manifest file privat di storage lokal sehingga cleanup dapat dilanjutkan jika terputus.

```bash
# Preview; tampilkan fingerprint database beserta jumlah baris
php artisan spmb:reset-operational

# Di .env lingkungan non-production yang sudah diverifikasi:
# SPMB_RESET_ALLOWED_TARGET=<fingerprint-dari-preview>
# php artisan config:clear

# HANYA setelah verifikasi target, backup+restore test, dan pemeriksaan VA:
php artisan spmb:reset-operational --execute \
  --target=<fingerprint-dari-preview> --backup-confirmed

# Jika database sudah di-commit tetapi cleanup file terputus:
php artisan spmb:reset-operational --cleanup-manifest=reset-manifests/<nama-file>.json
```

**Jangan gunakan `--execute` pada data berharga atau ketika VA riil pernah dipakai.** Periksa rencana penghapusan, data pelatihan/sertifikasi yang dimiliki akun pendaftar, hasil CI, serta backup terlebih dahulu. Versi saat ini belum merupakan pengganti backup operator maupun sistem rekonsiliasi bank. Perintah `spmb:dev-reset-preview` tetap tersedia sebagai ringkasan awal non-destruktif.

### Profil dan keamanan akun

**Profil staf mandiri ([PR #19](https://github.com/yusufbayuw/spmb/pull/19)):** Super Admin, Admin Unit, dan TU dapat membuka `/admin/profile` atau menu akun Filament. Staf dapat:

- memperbarui **nama**;
- melihat **username, email, role, dan unit** secara read-only;
- mengganti password setelah mengisi **password saat ini**, password baru, dan konfirmasi password (dengan aturan validasi Laravel).

Profil mandiri **tidak boleh mengubah email, username, role, unit, maupun status aktif**. Rotasi password menaikkan `auth_version` dan mengganti `remember_token` untuk menolak sesi staf lama, tetapi mempertahankan sesi yang berhasil melakukan perubahan. Login terpadu menyimpan generasi sesi staf terkini sehingga akun yang pernah mengganti password tetap dapat login normal.

Profil pendaftar ada di `/pendaftar/profile`. Bantuan pemulihan password oleh Admin Unit memakai token broker Laravel yang dikirim **langsung kepada pendaftar**; petugas tidak melihat token dan tidak dapat menentukan password pendaftar.

### Hardening password dan sesi staf

```bash
php artisan spmb:harden-staff-passwords
php artisan spmb:harden-staff-passwords --execute
```

Aksi non-production ini mempertahankan akun `super_admin`, `admin_unit`, dan `tu`, menghasilkan hash password acak berbeda untuk setiap akun, mengubah remember token, membatalkan token reset lama, serta mencabut sesi database lama. Kolom `auth_version` dan middleware sesi admin juga menolak sesi Redis/file generasi lama pada permintaan berikutnya setelah rotasi. Password baru tidak ditampilkan; **pastikan pemulihan email berfungsi sebelum rotasi**.

### Panduan validasi Fase 1.1

Panduan UAT dan verifikasi keamanan pada staging tersedia di [docs/PHASE_1_1_VALIDATION.md](docs/PHASE_1_1_VALIDATION.md). Semua checklist operasional bersifat manual dan tidak dianggap selesai hanya karena GitHub Actions berstatus hijau.

Untuk memeriksa jejak audit lama tanpa perubahan: `php artisan spmb:audit:review`. Sanitasi payload JSON lama memerlukan backup yang teruji, keputusan retensi, dan perintah `php artisan spmb:audit:review --execute --backup-confirmed` dengan konfirmasi interaktif. Teks bebas, path, IP dan backup lama perlu diperiksa terpisah.

### Keamanan akses, audit dan CI

- Policy melakukan pemeriksaan unit pada record selain pemeriksaan role/permission; resource yang tidak terkait penerimaan tetap menggunakan aturan akses khususnya.
- Data pribadi pada payload audit baru (misalnya nama, NIK, email, alamat, informasi keluarga, teks bebas) disamarkan. Audit historis memerlukan peninjauan/penanganan tersendiri; jangan menganggap log lama otomatis dibersihkan.
- GitHub CI menggunakan `composer install` dari lockfile dan permission `contents: read`. Upgrade dependency harus melalui perubahan kode yang ditinjau, bukan CI melakukan push otomatis.
- Jalankan tes pada SQLite dan pemeriksaan database yang sejenis dengan deployment sebenarnya; verifikasi restore backup serta prosedur operator tetap wajib.

### Pergantian tahun ajaran

Pembukaan pendaftaran menyimpan `academic_year` dan `wave`. **Arsip tahun lama, bukan hapus**, terutama saat daftar ulang tahun sebelumnya masih berjalan. Pembukaan tahun baru sebaiknya dibuat sebagai draft dengan konfigurasi yang ditinjau ulang, bukan menimpa record historis. Wizard rollover otomatis dan master tahun ajaran khusus **belum tersedia**; jangan menganggap reset development sebagai prosedur pergantian tahun.

### Checklist sebelum operasi destruktif

1. Pastikan environment, koneksi database, dan identitas deployment yang dituju.
2. Backup database **dan** file privat; uji pemulihan backup.
3. Rekonsiliasi VA, pembayaran, kuitansi, serta antrean notifikasi.
4. Jalankan preview; evaluasi data yang akan terhapus dan konfigurasi yang harus tetap ada.
5. Lakukan eksekusi hanya setelah dependensi, cakupan file, dan pengujian reset diselesaikan.

## Testing

Jalankan:

```bash
php artisan test
```

CI juga menjalankan dependency resolution, frontend build, migration, seeding untuk testing, dan full test suite. Regresi utama untuk pengembangan terbaru:

```bash
php artisan test --filter=RegistrationEmailDeliveryTest
php artisan test --filter=NotificationDeliveryManagerTest
php artisan test --filter=StaffProfileTest
```

Tes mencakup isolasi unit, pembatasan kirim ulang, syarat VA/pengumuman, reset password tanpa mengambil alih akun, identitas/konfirmasi email baru, pengingat ketika kondisi berubah, serta akses monitoring. CI hijau **tidak menggantikan** UAT SMTP nyata, pengujian scheduler/worker, validasi keamanan provider email, dan restore backup pada staging.

## Keamanan

Beberapa prinsip yang diterapkan:

- CSRF tetap aktif;
- cookie session berlaku lintas panel melalui path root;
- file pendaftar tidak dibuat public secara langsung;
- PWA tidak cache halaman authenticated;
- role dan permission dipisahkan dengan Spatie Permission;
- consent disimpan sebagai snapshot audit;
- Web Push bersifat opt-in per perangkat;
- Admin Unit hanya boleh memperbaiki komunikasi untuk unit sendiri, tidak dapat melewati tahap pendaftaran;
- koreksi email membutuhkan verifikasi identitas dan konfirmasi dari pemilik akun;
- token reset password tidak ditampilkan kepada petugas;
- pekerjaan di queue memvalidasi ulang tujuan email dan kondisi workflow.

## Struktur Portal

```text
Public
├── /
├── /penerimaan/...
├── /legal/terms
├── /legal/privacy
└── /login
        │
        ├── staff      → /admin
        │   ├── /admin/profile
        │   ├── /admin/registrations (Kirim Ulang Email / Koreksi Email)
        │   ├── /admin/notification-delivery-center
        │   └── /admin/applicant-account-recovery
        └── pendaftar  → /pendaftar
            └── /pendaftar/profile
```

Panel admin dan pendaftar tetap terpisah walaupun menggunakan satu gateway login.

## Lisensi

Tentukan lisensi repository sesuai kebijakan pemilik project sebelum distribusi publik.
