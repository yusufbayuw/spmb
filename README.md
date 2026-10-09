# SPMB

Platform penerimaan peserta didik dan mahasiswa berbasis Laravel dan Filament.

Repository ini bersifat **white-label**. Identitas institusi, nama portal, kontak, alamat, logo, unit pendidikan, program studi, dan data operasional tidak boleh di-hard-code ke source code. Setiap deployment mengatur identitasnya melalui environment/configuration dan data aplikasi.

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

- staf → `/admin`
- pendaftar → `/pendaftar`

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
- mode K12, perguruan tinggi, dan mixed.

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

Jangan menjalankan:

```bash
php artisan migrate:fresh
```

pada database production.

Menjalankan `php artisan db:seed --force` pada production hanya menjalankan reference seeder melalui `DatabaseSeeder`; demo data tidak dibuat.

## Queue

Contoh worker:

```bash
php artisan queue:work --queue=emails,notifications,default --sleep=3 --tries=5 --timeout=120
```

Pastikan queue worker dikelola oleh Supervisor/systemd atau process manager yang sesuai.

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

CI juga menjalankan dependency resolution, frontend build, migration, seeding untuk testing, dan full test suite.

## Keamanan

Beberapa prinsip yang diterapkan:

- CSRF tetap aktif;
- cookie session berlaku lintas panel melalui path root;
- file pendaftar tidak dibuat public secara langsung;
- PWA tidak cache halaman authenticated;
- role dan permission dipisahkan dengan Spatie Permission;
- consent disimpan sebagai snapshot audit;
- Web Push bersifat opt-in per perangkat.

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
        └── pendaftar  → /pendaftar
```

Panel admin dan pendaftar tetap terpisah walaupun menggunakan satu gateway login.

## Lisensi

Tentukan lisensi repository sesuai kebijakan pemilik project sebelum distribusi publik.
