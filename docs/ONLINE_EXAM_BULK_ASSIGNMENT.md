# Tes Online — Penugasan Massal dari Pembukaan Pendaftaran

Fitur ini **tidak** terhubung ke penilaian seleksi dan tidak memutasi data registrasi.

## Cara kerja

1. Buka **Admin → Tes Online → Peserta Tes Online → Penugasan Massal**.
2. Pilih **Paket Tes** yang sudah diterbitkan.
3. Pilih satu **Pembukaan Pendaftaran**. Label pembukaan menampilkan prodi, tahun ajaran, dan gelombang.
4. Opsional pilih **Jalur Pendaftaran** pada unit yang sama.
5. Klik **Pratinjau Peserta**. Panel menampilkan jumlah yang memenuhi syarat, akan dibuat, sudah ditugaskan, dan pernah dicabut.
6. Klik **Konfirmasi Penugasan** setelah memeriksa angka. Sistem memeriksa ulang daftar sebelum membuat penugasan.

## Kebijakan

- Hanya **Super Admin** dan **Admin Unit** yang memiliki akses unit yang dapat menjalankan proses.
- Ujian wajib sudah diterbitkan; pembukaan harus berasal dari unit yang sama, tidak diarsipkan, dan prodi sama jika paket membatasi prodi.
- Hanya registrasi dengan \`lifecycle_status = active\`, \`submitted_at\` terisi, pemilik akun aktif, dan role \`pendaftar\` yang dipilih.
- Penugasan **dideduplikasi berdasarkan akun dan paket**, bukan berdasarkan registrasi. Akun dengan lebih dari satu registrasi pada gelombang yang sama hanya memperoleh satu penugasan.
- Penugasan lama yang aktif tidak digandakan. Yang pernah dicabut **tidak diaktifkan kembali** tanpa aksi admin yang eksplisit.
- Maksimal 500 akun per eksekusi; gunakan pembukaan/jalur yang lebih sempit untuk kelompok lebih besar.
- Server melakukan penetapan dalam transaksi dan memverifikasi ulang cohort dari signature pratinjau; perubahan daftar/akses menggagalkan seluruh batch.
- Audit mencatat ID pembukaan, ID jalur, serta jumlah baru/aktif/dicabut, bukan daftar identitas pribadi.

## Batasan

- Penugasan dilakukan **sekali saat admin menekan konfirmasi**, bukan otomatis dari event registrasi.
- Tidak menambah jadwal ujian atau mengubah \`AdmissionTestResult\`, \`registrations.current_stage\`, dan hasil seleksi.
- Tidak mengirim notifikasi ke peserta pada iterasi ini.
- Filter gelombang melalui ID pembukaan, bukan pencocokan teks label gelombang.
- Setelah deployment, UAT lintas unit, perubahan cohort setelah pratinjau, pembatasan prodi, dan registrasi duplikat harus divalidasi pada staging.

## Release gate

- [ ] CI SQLite + MySQL untuk bulk assignment dan regresi ujian existing.
- [ ] UAT admin unit berbeda, pratinjau, penugasan berulang, pencabutan akses, dan perubahan peserta saat modal terbuka.
- [ ] Simulasi transaksi massal serta queue/scheduler yang tetap operasional.
- [ ] Backup database dan uji restore.
