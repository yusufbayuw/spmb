# Audit Kesiapan Production — SPMB

Dokumen ini adalah **gate rilis**, bukan sertifikasi keamanan mutlak. Dashboard Super Admin
tersedia pada **Sistem & Akses → Audit Siap Production** (`/admin/production-readiness-center`).
Halaman otomatis memeriksa konfigurasi dan data yang dapat diobservasi dari dalam aplikasi,
sementara bukti fisik dan end-to-end wajib dikonfirmasi manusia.

## Peran dan interpretasi

- **Super Admin aktif**: satu-satunya role yang boleh membuka dashboard, mengisi bukti, dan menyimpan snapshot.
- **Admin Unit, TU, pendaftar**: tidak dapat mengakses dashboard ataupun melakukan perubahan audit.
- **Lulus**: pemeriksaan otomatis pada runtime saat itu berhasil atau bukti manual disetujui untuk instalasi ini.
- **Gagal**: harus diperbaiki sebelum masuk production; detail yang sensitif disembunyikan.
- **Peringatan**: tidak otomatis dinyatakan aman; perlu tindakan sebelum semua cek dapat lulus.
- **Belum diverifikasi**: tidak dihitung lulus; bukti manual masih dibutuhkan.
- **BELUM SIAP PRODUCTION**: ada gagal, peringatan, atau bukti tertunda. **Jangan go-live**.
- **Seluruh pemeriksaan lulus — perlu persetujuan rilis manusia**: hasil pemeriksaan menjadi bahan keputusan; bukan persetujuan otomatis.

Semua permintaan evaluasi otomatis **read-only**: tidak mengirim email, menjalankan migrasi, menghapus job,
mengakses situs publik, membuat subscriber, atau mengubah data peserta. Super Admin secara sengaja dapat
menyimpan **bukti pemeriksaan** dan **snapshot historis** (tersimpan di database dan dicatat pada audit).

## 1. Persiapan instalasi / deployment

1. Backup database dan seluruh file privat, simpan salinan di lokasi terpisah dari server aplikasi.
2. Pastikan `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`.
3. Tetapkan `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` atau `strict`.
4. Gunakan driver persistent untuk session dan queue (Redis/database), bukan `sync` atau `array` di production.
5. Konfigurasikan SMTP nyata, `MAIL_FROM_ADDRESS` yang valid, SPF, DKIM dan DMARC.
6. Tetapkan VAPID public/private yang benar dan jangan mengganti kunci pada setiap deployment.
7. Terapkan pengaturan operasional berikut; tidak perlu Git, `git rev-parse`, atau `SPMB_RELEASE_SHA`:

```dotenv
SPMB_READINESS_PROBES_ENABLED=true
SPMB_MAIL_QUEUE=emails
SPMB_NOTIFICATION_QUEUE=notifications
SPMB_AUTOMATIC_REMINDERS_ENABLED=false
```

8. Perbarui code/dependency, jalankan migrasi **sebelum memulai worker baru**, lalu `php artisan optimize:clear` dan `php artisan config:cache`.
9. Jalankan worker `emails,notifications,default` di Supervisor/systemd dan cron `schedule:run` **setiap menit**.
10. Setelah sekitar dua siklus probe **5 menit**, buka dashboard dan pastikan heartbeat terdeteksi.
11. Gunakan `php artisan spmb:release:preflight --profile=production --json` sebagai pemeriksaan CLI tambahan.
12. Cek `php artisan queue:failed` dan `php artisan schedule:list`. Jangan menghapus job gagal tanpa root-cause analysis.

Bukti manual melekat pada instalasi aplikasi berdasarkan identitas deployment, bukan SHA kode.
Copy-paste kode **tidak menghapus atau mengulang** persetujuan yang masih relevan. Setelah perubahan signifikan
pada infrastruktur, keamanan, atau workflow, Super Admin perlu memeriksa ulang bukti terkait.
Pemeriksaan fungsi otomatis tetap dijalankan ulang setiap kali dashboard dibuka.
`SPMB_READINESS_PROBES_ENABLED`
adalah **opt-in** (default `false`), memasukkan job kecil pada queue email dan notifikasi
setiap 5 menit serta menyimpan heartbeat ke cache. Bila cron, cache, queue, worker, atau probe tidak
berfungsi, dashboard akan gagal pada pemeriksaan liveness. Menyalakan probe memerlukan scheduler aktif.

## 2. Checklist otomatis

Klasifikasi di dashboard:

| Kategori | Pemeriksaan |
| --- | --- |
| Release | Identitas instalasi audit (tanpa ketergantungan Git atau SHA) |
| Konfigurasi | environment production, APP_KEY, DEBUG off, sesi persistent, queue non-sync |
| Keamanan | HTTPS, cookie secure/HTTP-only/SameSite, reset development disabled, storage privat |
| Email | mailer nyata, sender non-demo, antrean tertahan dan gagal dalam 24 jam |
| Database | SELECT 1, migrasi lengkap, semua tabel bisnis dan notifikasi penting tersedia |
| Queue | failed_jobs kosong; jika ada gagal, harus investigasi penyebab terlebih dahulu |
| PWA/Push | VAPID keys, ikon 192/512/maskable, service worker, script PWA, manifest, endpoint subscribe |
| Monitoring | heartbeat Laravel Scheduler, worker `emails`, dan worker `notifications` dalam 12 menit |

**Batasan:** pengecekan database hanya memastikan koneksi, tabel, dan migrasi yang dapat diobservasi.
Tidak memeriksa indeks/performa pada data besar, query plan, lock panjang, downtime, kebocoran data,
kekuatan kriptografi VAPID, atau browser di perangkat nyata. Pemeriksaan URL memastikan **konfigurasi** `APP_URL`
menggunakan HTTPS, bukan sertifikat publik atau reverse proxy benar: pemeriksaan tersebut tetap wajib manual.

## 3. Database notification dan PWA / Web Push

- Pendaftaran baru memicu database notification untuk pendaftar sendiri dan staf dalam unit terkait/Super Admin.
- Filament admin dan pendaftar mengaktifkan notification database polling default **15 detik**.
- Notifikasi mendukung `read_at`, aksi `markAsRead`, UUID, event/kategori dan konteks registrasi/unit.
- Kanal database dikonfigurasi idempotent berdasarkan ID notifikasi untuk meminimalkan duplikasi retry.
- Jika VAPID dan subscription ada, job notifikasi juga menggunakan kanal Web Push.
- Endpoint subscription memerlukan autentikasi dan HTTPS serta **host provider push yang diizinkan**.
- Tidak menerima endpoint IP lokal, host `localhost`, port selain 443, atau host asing; ini meminimalkan
  penyalahgunaan server sebagai request outbound. Operator tetap harus menguji **egress firewall dan DNS**
  terhadap risiko jaringan yang tidak bisa diverifikasi oleh PHP.
- Default host yang diperbolehkan: `fcm.googleapis.com`, `updates.push.services.mozilla.com`,
  subdomain `push.apple.com`, `web.push.apple.com`, subdomain `notify.windows.com`
  dan `wns.windows.com`. Jika browser sah menggunakan provider lain, telaah dokumen provider terlebih dahulu,
  baru tambahkan melalui `SPMB_PUSH_ALLOWED_HOSTS` (daftar dipisahkan koma).
- Service worker hanya menangani `push` dan `notificationclick`, **tanpa `fetch` cache** untuk
  halaman login, dokumen, informasi pembayaran atau data pribadi.
- Navigasi saat klik notifikasi harus tetap pada **same-origin**.
- Uji instalasi, pembaruan, opt-in, opt-out, pengiriman nyata foreground/background,
  interaksi tautan, logout dan login ulang pada browser dan perangkat pengguna, khususnya iOS Safari PWA.

**Uji manual wajib**: daftarkan dua perangkat milik dua akun berbeda, kirim event hanya untuk
satu akun, pastikan perangkat akun lain tidak menerimanya. Coba unsubscribe di salah satu perangkat,
uji pengiriman berikutnya, dan konfirmasi tidak ada token subscription terekspos ke publik.

## 4. Checklist manual yang menghalangi go-live

Pengujian berikut harus menghasilkan **bukti audit** dengan lokasi dokumen/tiket dan hasil yang jelas.
Setiap nilai `pass` membutuhkan keterangan bukti sekurang-kurangnya 20 karakter; jangan masukkan
password, API key, NIK, alamat lengkap, atau token ke form bukti.

| Kelompok | Bukti yang wajib disiapkan |
| --- | --- |
| Backup | Restore DB **dan** private files berhasil; uji RPO, RTO, retensi, dan petugas on-call |
| Release | Approved commit tepat, rollback kompatibel, isolasi staging-production, sign-off |
| Queue | Supervisor restart/recovery, cron berjalan, heartbeat kedua worker |
| Email | Inbox eksternal nyata, SPF/DKIM/DMARC, simulasi gagal SMTP dan retry |
| Notifikasi | In-app per user/unit, unread/read, polling, push background dan opt-out |
| PWA | Instalasi Android/iOS, logout, update SW, tidak ada dokumen sensitif di cache |
| Keamanan | TLS/headers, batas otorisasi (IDOR), file rahasia tidak tersedia dari webroot, rate limit, CAPTCHA |
| Upload | Malware scanning, MIME/size, file private, download scoped, invalid file ditolak |
| Alur bisnis | Pendaftaran hingga seleksi/daftar ulang; pembayaran berdasarkan bukti transfer; tidak ada VA/Payment duplikat |
| Operasional | Load test, observabilitas/alerting, disk, DB, Redis, backup, antrian |
| Privasi | Retensi, consent, akses staf, audit log, penghapusan data terkontrol |

Lakukan uji pada **staging yang terisolasi**, jangan menggunakan akun/email peserta riil untuk destructive test.
Setiap screenshot/laporan internal harus disimpan pada lokasi akses terbatas dan dirujuk lewat nomor tiket
di UI — bukti lengkapnya tidak perlu disalin ke database aplikasi.

## 5. Prosedur Super Admin

1. Buka **Sistem & Akses → Audit Siap Production**.
2. Periksa tiap kategori otomatis. Semua status gagal/peringatan memblokir keputusan go-live.
3. Jalankan tes manual dari checklist dan simpan bukti pada form **Validasi manual**.
4. Untuk kontrol yang belum berhasil, pilih `fail` atau `pending`, bukan `pass`.
5. Pilih **Simpan snapshot audit saat ini** agar kondisi saat pemeriksaan tersimpan dan audit log bertambah.
6. Review dengan pemilik sistem, operator infrastruktur, keamanan, dan pemilik bisnis.
7. Beri keputusan go/no-go melalui proses persetujuan institusi di luar sistem. Dashboard tidak
   menerapkan perubahan deployment dan tidak dapat mem-bypass persetujuan manusia.
8. Jika kode berubah lewat copy-paste, pemeriksaan otomatis langsung menilai kondisi runtime baru. Validasi ulang secara manual setiap bukti yang terdampak perubahan tersebut; audit lama tidak otomatis dihapus.

## 6. Kriteria produksi dan sisa risiko

**NO-GO:** salah satu pemeriksaan merah/peringatan/pending, restore backup belum teruji,
worker tidak hidup, SMTP belum diuji, push belum diuji pada perangkat yang ditargetkan,
atau isolasi staging-production tidak terbukti.

**Candidate / Go for human review:** seluruh pemeriksaan otomatis dan checklist manual lulus,
bukti masih relevan, backup-restore teruji, dan operator memiliki rollback/monitoring.
Tetap perlu persetujuan institusi serta pengawasan aktif setelah deployment.

**Tidak pernah dijanjikan**: aplikasi tanpa bug, pasti tidak dapat diretas, 100% delivery email,
push selalu tampil pada semua versi browser/OS, atau tanpa kendala di infrastruktur aktual.
Hasil CI GitHub tidak dapat mengetahui kondisi MySQL/Redis/aaPanel, TLS/proxy, SMTP, firewall,
worker yang berjalan pada server tujuan, atau keberhasilan restore backup.

## 7. Pemeriksaan regresi

```bash
php artisan test --filter=ProductionReadinessCenterTest
php artisan test --filter=ProductionNotificationIntegrationTest
php artisan test --filter=FilamentNotificationTest
php artisan test --filter=PushSubscriptionTest
php artisan test --filter=PwaAssetsTest
php artisan test --filter=ReleasePreflightTest
php artisan test
```

Jangan menggunakan `migrate:fresh` maupun `db:seed DemoSeeder` pada database production.
