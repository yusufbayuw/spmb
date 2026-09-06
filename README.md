# SPMB Taruna Bakti

Sistem Penerimaan Murid/Mahasiswa Baru (SPMB) Taruna Bakti berbasis Laravel 12 dan Filament 3. Aplikasi ini melayani pendaftaran untuk Daycare, KB, TK, SD, SMP, SMA, dan Taruna Bakti University (TBU).

Setiap unit memiliki konfigurasi pendaftaran sendiri. Konfigurasi yang telah dipublikasikan disalin sebagai versi pada pendaftaran baru, sehingga perubahan aturan unit tidak mengubah persyaratan peserta yang sudah mendaftar.

## Peran dan portal

| Peran | Portal | Tanggung jawab |
| --- | --- | --- |
| Pendaftar | `/pendaftar` | Membuat pendaftaran, melengkapi data dan berkas, mengunggah pembayaran, memilih sesi tes, serta mencetak dokumen. |
| TU unit | `/admin` | Mengelola pembukaan, konfigurasi unit sendiri, verifikasi, sesi tes, seleksi, dan tindak lanjut pendaftar unit. |
| Super admin | `/admin` | Mengelola seluruh unit, master data, akses, dan laporan operasional. |

Pendaftar harus memverifikasi alamat email sebelum dapat membuat pendaftaran.

## Fitur utama

### Konfigurasi pendaftaran per unit

Menu **Pengaturan Pendaftaran Unit** memungkinkan TU membuat draft dan mempublikasikan versi konfigurasi unit. Konfigurasi meliputi:

- tahap pembayaran, dokumen, dan tes yang dapat diaktifkan per unit;
- field tambahan pada formulir, termasuk label, petunjuk, kelompok, urutan, wajib, aktif, dan jenis input;
- persyaratan dokumen dengan format jawaban, jumlah lampiran, status wajib/aktif, instruksi, dan template PDF/DOCX;
- tes wajib yang berlaku pada versi konfigurasi tersebut;
- pratinjau formulir pendaftar sebelum publikasi.

Identitas inti pendaftar, pilihan pendaftaran, validasi usia, dan aturan satu pendaftaran tetap dijaga sistem. Pembayaran yang dinonaktifkan hanya dapat digunakan pada pembukaan dengan biaya nol.

### Alur pendaftaran

Alur dasar adalah validasi data, pembayaran bila aktif, penerbitan kartu pendaftar, dokumen bila aktif, tes bila aktif, seleksi, dan pengumuman. Sistem melewati tahap nonaktif menurut versi konfigurasi yang terikat pada pendaftaran.

Pendaftar dapat mengunggah beberapa lampiran bila diizinkan. Dokumen wajib selesai jika ada minimal satu lampiran dan seluruh lampiran yang diajukan telah diverifikasi. File yang ditolak dapat diganti; keputusan verifikasi lama akan dibuka kembali untuk diperiksa TU.

### Tes, kartu, dan kuitansi

- TU membuat beberapa sesi per jenis tes dengan waktu, lokasi, kuota, status, dan batas pemesanan.
- Pendaftar memilih atau memindahkan sesi selama batas pemesanan masih terbuka, kuota tersedia, dan jadwal tidak berbenturan.
- Pemesanan memakai transaksi dan penguncian database agar kursi terakhir tidak terisi melebihi kuota.
- Kartu pendaftar, kartu tes, dan kuitansi bukti lunas tersedia sebagai halaman cetak HTML.
- Kuitansi hanya diterbitkan setelah pembayaran terverifikasi dan menyimpan nomor serta rincian transaksi saat diterbitkan.

### Notifikasi dan keamanan

Notifikasi database dikirim setelah transaksi berhasil untuk pendaftar dan TU terkait, termasuk pengiriman/revisi pendaftaran, verifikasi dokumen atau pembayaran, pemilihan/perpindahan/pembatalan sesi, kesiapan seleksi, dan pengumuman. Kanal notifikasi bersifat idempoten sehingga retry tidak membuat duplikasi.

Semua model SPMB menggunakan UUID publik yang tidak dapat diubah. UUID digunakan pada URL, route binding, state pendaftar/TU, tautan cetak, unduhan private, verifikasi email, dan payload notifikasi. ID numerik tetap menjadi detail relasional internal database.

Unggahan pendaftar dan template disimpan pada disk private. Format file, ukuran, signature, struktur DOCX, dan opsi pemindaian malware diperiksa di server. Template dan lampiran hanya dapat diunduh oleh pemilik pendaftaran atau petugas unit yang berwenang.

## Kebutuhan

- PHP 8.3 atau lebih baru
- Composer
- Node.js dan npm
- Database yang didukung Laravel
- Worker antrean untuk email dan notifikasi pada staging/produksi

## Instalasi lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Atur koneksi database pada `.env`, lalu jalankan migrasi, data awal, dan aset frontend:

```bash
php artisan migrate --seed
npm install
npm run build
```

Untuk pengembangan frontend, gunakan:

```bash
npm run dev
```

Laravel Herd dapat melayani aplikasi lokal tanpa menjalankan `php artisan serve`.

## Data awal

Seeder membuat unit, jalur reguler, program studi TBU, konfigurasi pendaftaran awal, pembukaan contoh, peran, pengguna TU per unit, sesi tes awal yang masih tertutup, serta kuitansi bagi pembayaran yang sudah terverifikasi.

Akun lokal awal:

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

Data tersebut hanya untuk lingkungan lokal/demo. Ganti kredensial dan buka sesi/pembukaan setelah data operasional diperiksa.

## Konfigurasi operasional

Variabel SPMB yang tersedia pada `.env`:

```dotenv
SPMB_UPLOAD_MAX_KB=5120
SPMB_UPLOAD_REQUIRE_MALWARE_SCAN=false
SPMB_CLAMAV_BINARY=clamscan
SPMB_CLAMAV_TIMEOUT=30
SPMB_MAIL_QUEUE=emails
SPMB_NOTIFICATION_QUEUE=notifications
SPMB_NOTIFICATION_POLLING=15s
```

Aktifkan `SPMB_UPLOAD_REQUIRE_MALWARE_SCAN=true` hanya jika `clamscan` tersedia pada server. Pada staging dan produksi, jalankan worker untuk antrean email dan notifikasi, misalnya:

```bash
php artisan queue:work --queue=emails,notifications,default --tries=5
```

Pastikan `APP_URL`, konfigurasi mail, `QUEUE_CONNECTION`, dan akses private storage telah sesuai lingkungan sebelum membuka pendaftaran.

## Pemeriksaan kualitas

Jalankan pemeriksaan berikut sebelum merge atau rollout:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run build
```

Suite pengujian mencakup isolasi antarunit, versi konfigurasi, formulir dan dokumen custom, template dan unggah ulang, sesi tes/kuota/perpindahan/pembatalan, cetakan, kuitansi, UUID publik, migrasi notifikasi lama, serta otorisasi pendaftar dan TU.

## Rollout

Terapkan lebih dahulu pada lokal atau staging, jalankan migrasi tanpa menghapus data operasional, aktifkan worker, lalu periksa pembukaan, alur pendaftar, notifikasi, dan cetakan. Penerapan produksi dilakukan sebagai langkah terpisah setelah pemeriksaan staging selesai.
