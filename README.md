# SPMB Taruna Bakti

Sistem Penerimaan Murid/Mahasiswa Baru (SPMB) Taruna Bakti untuk Daycare, KB, TK, SD, SMP, SMA, dan Taruna Bakti University (TBU).

Aplikasi dibangun dengan Laravel 12 dan Filament 3. Setiap unit memiliki pembukaan, jalur, kuota, konfigurasi formulir, persyaratan dokumen, tes, seleksi, pengumuman, serta alur daftar ulang yang dapat berbeda. Konfigurasi pendaftaran menggunakan versi yang dipublikasikan dan disimpan pada pendaftaran, sehingga perubahan konfigurasi berikutnya tidak mengubah aturan peserta yang sudah masuk.

## Stack utama

- PHP 8.3+
- Laravel 12
- Filament 3.2
- Filament Shield 3.x + Spatie Laravel Permission
- `mortezaashrafi/filament-shield-captcha` untuk CAPTCHA lokal
- OpenSpout untuk impor/ekspor XLSX hasil tes
- Laravel database queue, notification, mail, dan private filesystem
- Vite untuk build frontend

## Portal dan peran

| Peran | Portal | Ruang lingkup |
| --- | --- | --- |
| Pendaftar | `/pendaftar` | Registrasi akun, membuat pendaftaran, melengkapi data, pembayaran, dokumen, jadwal tes, pengumuman, dan daftar ulang. |
| TU unit | `/admin` | Operasional harian SPMB pada unitnya: pendaftaran, verifikasi pembayaran/dokumen, hasil tes, seleksi, pengumuman, dan daftar ulang. Tidak memiliki akses ke group **Konfigurasi SPMB** dan **Sistem & Akses**. |
| Admin Unit | `/admin` | Memiliki kewenangan unit yang sebelumnya dimiliki TU, termasuk operasional dan konfigurasi SPMB pada unitnya, tetap dengan scope satu unit. |
| Super admin | `/admin` | Mengelola seluruh unit, master data, user, role/permission, konfigurasi, dan audit. |

Akses admin menggunakan permission Filament Shield. TU dan Admin Unit sama-sama dibatasi ke unit yang terkait; perbedaannya berada pada permission. Admin Unit membawa baseline permission TU lama, sedangkan TU difokuskan ke workflow operasional. Super admin dapat bekerja lintas unit.

## Alur utama

```text
Akun Pendaftar
    -> Verifikasi Email
    -> Pilih Pembukaan / Jalur
    -> Isi Formulir Pendaftaran
    -> Pembayaran Formulir (jika diaktifkan)
    -> Verifikasi Pembayaran
    -> Nomor Registrasi + Kartu Pendaftar
    -> Dokumen Persyaratan (jika diaktifkan)
    -> Penjadwalan Tes (jika diaktifkan)
    -> Pelaksanaan & Hasil Tes
    -> Seleksi / Penetapan Hasil
    -> Pengumuman
    -> Admission Offer
    -> Daftar Ulang (jika diaktifkan)
    -> Enrollment
```

Tahap yang dinonaktifkan oleh konfigurasi unit dilewati oleh workflow. Untuk pembukaan berbiaya nol, tahap pembayaran dapat dinonaktifkan.

## Konfigurasi pendaftaran per unit

Menu **Pengaturan Pendaftaran Unit** menjadi pusat konfigurasi operasional per unit dan hanya dapat diakses oleh Admin Unit pada unitnya atau Super Admin. Konfigurasi dapat disimpan sebagai draft, dipratinjau, lalu dipublikasikan sebagai versi baru.

Konfigurasi mencakup:

- aktivasi tahap pembayaran, dokumen, tes, dan daftar ulang;
- field tambahan pada formulir pendaftar: label, petunjuk, kelompok, urutan, jenis input, wajib, dan status aktif;
- persyaratan dokumen beserta instruksi, tipe jawaban, jumlah lampiran, template, status wajib, dan status aktif;
- jenis tes yang berlaku pada unit beserta status wajib/opsional;
- sesi tes terkait jenis tes, termasuk waktu mulai/selesai, lokasi, kuota, batas pemesanan, instruksi, dan status sesi;
- pratinjau konfigurasi sebelum publikasi.

Identitas inti pendaftar, pilihan pembukaan/jalur, aturan usia, dan constraint utama pendaftaran tetap dijaga oleh domain aplikasi.

## Formulir dan data wilayah Indonesia

Formulir pendaftar mendukung data alamat terstruktur melalui select berjenjang:

```text
Provinsi
  -> Kabupaten/Kota
      -> Kecamatan
          -> Desa/Kelurahan
```

Dataset wilayah Indonesia lengkap dibundel di repository dan disimpan sebagai master lokal, sehingga form tidak bergantung pada API wilayah eksternal. Kode wilayah dan nama kanonik disimpan bersama data pendaftar. Field wilayah dibuat aman untuk data lama yang masih `null`.

Antarmuka pendaftar menggunakan istilah Indonesia dan action utama pendaftaran ditampilkan sebagai **Daftar**.

## Pembayaran, Virtual Account, dan nomor registrasi

Aplikasi memiliki master Virtual Account dan proses verifikasi pembayaran formulir. Pada flow berbayar:

- pendaftaran dapat berada pada tahap pembayaran tanpa nomor registrasi resmi;
- nomor registrasi baru dialokasikan setelah pembayaran terverifikasi;
- nomor registrasi memiliki sequence independen per unit (`0001`, `0002`, dan seterusnya sesuai unit);
- alokasi nomor dibuat atomic untuk mencegah nomor ganda saat verifikasi bersamaan;
- kartu pendaftar diterbitkan otomatis setelah pembayaran terverifikasi dan nomor resmi tersedia;
- kuitansi bukti lunas menggunakan snapshot nomor registrasi dan data transaksi saat diterbitkan.

Pembayaran yang belum menghasilkan nomor registrasi tetap dapat dikelola tanpa memaksa nomor sementara.

## Dokumen pendaftar

Persyaratan dokumen mengikuti versi konfigurasi yang terikat pada pendaftaran. Sistem mendukung satu atau beberapa lampiran sesuai konfigurasi.

Status dokumen wajib dianggap selesai jika persyaratan lampiran terpenuhi dan seluruh lampiran yang diajukan sudah diverifikasi. Lampiran yang ditolak dapat diganti; unggahan pengganti membuka kembali proses verifikasi untuk TU.

Template dan dokumen pendaftar disimpan pada private storage. Download hanya dilayani melalui route terotorisasi.

## Tes dan penjadwalan

Tes dikelola sebagai `AdmissionTest`, `TestSession`, `TestBooking`, dan `AdmissionTestResult`.

- Satu unit dapat memiliki beberapa jenis tes wajib maupun opsional.
- Setiap jenis tes dapat memiliki beberapa sesi dengan waktu, lokasi, kuota, batas pemesanan, instruksi, dan status.
- Pendaftar dapat memilih atau memindahkan sesi selama sesi masih tersedia dan aturan booking terpenuhi.
- TU dapat menetapkan sesi secara manual untuk kebutuhan operasional.
- Admin Unit dapat mengelola master dan sesi tes pada konfigurasi unit.
- Booking menggunakan transaksi/locking untuk menjaga kapasitas sesi.
- Sistem mencegah jadwal tes yang berbenturan.
- Jika sesi terpilih dibatalkan, pendaftar harus melakukan penjadwalan ulang.
- **Kartu Tes hanya dapat dicetak setelah seluruh tes wajib memiliki sesi.** Tes opsional tidak menahan hak cetak kartu.
- Seluruh waktu operasional ditampilkan dalam format 24 jam.

## Hasil tes

Pengisian hasil tes mendukung workflow batch berbasis XLSX. TU dapat mengunduh data hasil tes, mengedit nilai di spreadsheet, lalu mengimpor kembali hasil secara massal menggunakan OpenSpout.

Resource hasil tes tetap menyediakan status dan hasil per kombinasi pendaftaran–jenis tes, dengan validasi agar peserta yang belum mencapai tahap tes tidak masuk ke proses hasil secara prematur.

## Seleksi, pengumuman, dan koreksi human error

Modul seleksi mencakup kuota penerimaan, batch seleksi, keputusan per pendaftar, dan pengumuman hasil.

Aplikasi menyediakan:

- penetapan keputusan seleksi;
- bulk action untuk keputusan banyak pendaftar sekaligus;
- koreksi keputusan secara terkontrol jika terjadi human error;
- publikasi pengumuman secara bulk;
- koreksi hasil yang sudah dipublikasikan melalui workflow yang tetap tercatat;
- audit log untuk perubahan penting.

Nilai/prestasi bukan bagian dari indikator progress tahapan pendaftar; progress berfokus pada langkah operasional yang memang harus diselesaikan.

## Admission Offer dan daftar ulang

Peserta yang diterima dapat memiliki `AdmissionOffer`. Bila daftar ulang diaktifkan pada konfigurasi unit, pendaftar mendapatkan workflow daftar ulang terpisah dengan item persyaratan yang dapat diverifikasi TU.

Navigasi daftar ulang hanya ditampilkan ketika fitur tersebut memang aktif dan relevan untuk pendaftar. Hasil review daftar ulang dikirimkan melalui notifikasi, dan proses dapat berlanjut sampai enrollment.

## Notifikasi

Aplikasi menggunakan database notification dan antrean untuk event operasional utama, antara lain:

- pendaftaran dibuat/diperbarui;
- pembayaran diverifikasi;
- dokumen diverifikasi atau perlu revisi;
- perubahan jadwal tes;
- kesiapan proses seleksi;
- hasil/pengumuman;
- admission offer dan daftar ulang.

Notifikasi dibuat setelah transaksi domain berhasil dan mekanismenya dirancang idempoten agar retry queue tidak menghasilkan notifikasi ganda.

## Keamanan

### UUID publik

Model SPMB utama menggunakan UUID untuk identifier yang diekspos ke URL, route binding, state panel, link cetak, download private, dan payload notifikasi. ID numerik tetap digunakan sebagai detail relasional internal database.

### Role dan permission

Authorization admin menggunakan Filament Shield dan Spatie Laravel Permission. Role tidak dijadikan satu-satunya sumber otorisasi; permission dan scope unit tetap menjadi pembatas operasi.

Role `admin_unit` dan `tu` sama-sama unit-scoped. `admin_unit` mempertahankan baseline akses TU sebelum pemisahan role, sedangkan `tu` tidak memiliki permission resource pada group **Konfigurasi SPMB** dan **Sistem & Akses**. Halaman konfigurasi khusus juga memeriksa role Admin Unit/Super Admin secara eksplisit.

Untuk database existing, perubahan role dapat diterapkan secara idempoten tanpa mengubah assignment user atau data bisnis:

```bash
php artisan db:seed --class=AdminUnitRoleSeeder
```

Seeder tersebut hanya menyinkronkan permission role `admin_unit` dan `tu`; user TU existing tetap menjadi TU sampai diubah secara eksplisit oleh administrator.

### CAPTCHA lokal

Login dan flow autentikasi menggunakan CAPTCHA lokal melalui `filament-shield-captcha`. Tidak dibutuhkan akun Cloudflare Turnstile, Google reCAPTCHA, atau provider CAPTCHA eksternal.

Konfigurasi CAPTCHA tersedia di `.env` melalui variabel `FILAMENT_SHIELD_CAPTCHA_*`.

### Private upload

Upload pendaftar dan template disimpan secara private. Aplikasi memvalidasi tipe/ukuran file dan memiliki opsi pemindaian malware menggunakan ClamAV.

## Lokalisasi UI

Aplikasi menggunakan locale Indonesia (`id`) dan timezone `Asia/Jakarta`. Format tanggal, angka, nominal, validation message, label UI, dan notifikasi telah diselaraskan untuk penggunaan Indonesia. Komponen waktu Filament menggunakan format 24 jam.

## Resource admin utama

Panel admin saat ini mencakup resource untuk:

- Unit dan User;
- Pembukaan Pendaftaran dan Jalur Pendaftaran;
- Program Studi TBU;
- Pendaftaran;
- Virtual Account dan Pembayaran;
- Dokumen dan Data Orang Tua;
- Tes Masuk dan Hasil Tes;
- Kuota Penerimaan dan Batch Seleksi;
- Seleksi dan Pengumuman;
- Item Daftar Ulang;
- Audit Log.

Sebagian workflow operasional ditempatkan sebagai action/page khusus agar perubahan status tidak dilakukan melalui edit bebas.

## Kebutuhan sistem

- PHP 8.3 atau lebih baru
- Composer
- Node.js dan npm
- Ekstensi PHP yang dibutuhkan Laravel/Filament
- Database yang didukung Laravel
- Queue worker untuk email/notifikasi pada staging dan production
- ClamAV bila malware scan diwajibkan

## Instalasi lokal

```bash
git clone <repository-url>
cd spmb
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Atur database dan konfigurasi environment pada `.env`, kemudian:

```bash
php artisan migrate --seed
npm run build
```

Untuk development frontend:

```bash
npm run dev
```

Repository juga menyediakan Composer setup script:

```bash
composer run setup
```

Laravel Herd dapat digunakan untuk melayani aplikasi lokal tanpa `php artisan serve`.

## Data demo

`DatabaseSeeder` saat ini menyiapkan unit, program studi, jalur, konfigurasi pendaftaran, pembukaan, kuota, role/permission, user admin/TU, sesi tes, dan kuitansi contoh.

Akun demo lokal:

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Super admin | `admin@tarunabakti.sch.id` | `password123` |
| TU Daycare | `tu.dc@tarunabakti.sch.id` | `password123` |
| TU KB | `tu.kb@tarunabakti.sch.id` | `password123` |
| TU TK | `tu.tk@tarunabakti.sch.id` | `password123` |
| TU SD | `tu.sd@tarunabakti.sch.id` | `password123` |
| TU SMP | `tu.smp@tarunabakti.sch.id` | `password123` |
| TU SMA | `tu.sma@tarunabakti.sch.id` | `password123` |
| TU TBU | `tu.tbu@tbu.ac.id` | `password123` |

Akun tersebut hanya untuk local/development. Jangan gunakan credential demo pada production.

## Konfigurasi environment SPMB

Contoh konfigurasi operasional dari `.env.example`:

```dotenv
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

SPMB_UPLOAD_MAX_KB=5120
SPMB_UPLOAD_REQUIRE_MALWARE_SCAN=false
SPMB_CLAMAV_BINARY=clamscan
SPMB_CLAMAV_TIMEOUT=30
SPMB_MAIL_QUEUE=emails
SPMB_NOTIFICATION_QUEUE=notifications
SPMB_NOTIFICATION_POLLING=15s

FILAMENT_SHIELD_CAPTCHA_TTL=300
FILAMENT_SHIELD_CAPTCHA_MAX_ATTEMPTS=5
FILAMENT_SHIELD_CAPTCHA_WIDTH=300
FILAMENT_SHIELD_CAPTCHA_HEIGHT=64
FILAMENT_SHIELD_CAPTCHA_LENGTH=5
FILAMENT_SHIELD_CAPTCHA_MODE=custom
FILAMENT_SHIELD_CAPTCHA_CHARSET=ABCDEFGHJKLMNPQRSTUVWXYZ23456789
FILAMENT_SHIELD_CAPTCHA_CASE_SENSITIVE=false
```

Jika `SPMB_UPLOAD_REQUIRE_MALWARE_SCAN=true`, pastikan binary `clamscan` tersedia dan dapat dijalankan oleh user PHP/web server.

## Queue

Pada staging/production jalankan worker untuk antrean aplikasi, misalnya:

```bash
php artisan queue:work --queue=emails,notifications,default --tries=5
```

Gunakan Supervisor/systemd atau process manager lain agar worker selalu aktif.

## Pemeriksaan kualitas

Sebelum rollout, minimal jalankan:

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Suite pengujian mencakup antara lain isolasi unit, konfigurasi versi, custom field/document, pembayaran dan nomor registrasi, data wilayah Indonesia, penjadwalan tes, kapasitas sesi, kartu tes, hasil tes, seleksi, pengumuman, daftar ulang, private file, UUID publik, notifikasi, role Admin Unit/TU, dan authorization.

## Catatan production

Sebelum production, pastikan `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` dan timezone benar, database sudah dibackup, mail/queue terkonfigurasi, storage persisten, worker aktif, permission storage sesuai, HTTPS aktif, dan credential demo tidak digunakan.

Deploy database menggunakan migrasi normal tanpa `migrate:fresh`:

```bash
php artisan migrate --force
php artisan optimize
```

Jika upgrade dari versi sebelum pemisahan Admin Unit/TU, jalankan juga:

```bash
php artisan db:seed --class=AdminUnitRoleSeeder
```

Jika frontend berubah, build asset production dengan `npm run build` sebelum switch release.

## Status aplikasi

Core flow SPMB dari konfigurasi unit, pendaftaran, pembayaran, nomor registrasi, dokumen, tes, hasil tes, seleksi, pengumuman, admission offer, hingga daftar ulang sudah tersedia dan dilindungi oleh test suite. Pengembangan berikutnya sebaiknya tetap mempertahankan versioned configuration, isolasi antarunit, private file access, permission berbasis Shield, dan perubahan state melalui service/action domain yang terkontrol.
