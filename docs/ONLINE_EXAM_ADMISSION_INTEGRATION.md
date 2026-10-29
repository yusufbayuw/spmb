# Integrasi CBT dan Hasil Tes Penerimaan — persetujuan staf

**Kebijakan: CBT hanya fasilitas tambahan, bukan bagian wajib alur pendaftaran SPMB.**
Pendaftar boleh menjalani seluruh proses SPMB tanpa paket, penugasan, atau nilai CBT.
Paket CBT tanpa pemetaan ke Tes Penerimaan tetap mandiri; bahkan jika ada paket
yang dipetakan, penugasan/pengerjaan/penilaian CBT tidak menambah tahap, tidak
menjadi prasyarat Seleksi, dan tidak mengubah hasil tes atau keputusan SPMB.

**Tes Penerimaan pada konfigurasi unit adalah domain terpisah dari CBT.**
Jika institusi mewajibkan Tes Penerimaan tertentu melalui konfigurasi SPMB,
persyaratan tersebut tetap mengikuti proses hasil tes normal dan dapat dipenuhi
tanpa CBT; tidak boleh ditafsirkan bahwa peserta wajib memakai fitur CBT.

Integrasi nilai hanya merupakan **pilihan eksplisit petugas berwenang**:
petugas terlebih dahulu memilih Tes Penerimaan saat mengelola paket dan secara
terpisah mengesahkan hasil final CBT ke pendaftaran yang cocok.
Tidak ada transfer nilai otomatis, penetapan kelulusan seleksi, atau pembaruan
pendaftaran saat peserta melakukan submit.

## Alur kerja (opsional, abaikan seluruh bagian ini untuk CBT mandiri)
1. Admin Unit/Super Admin membuka **Tes Online → Paket Tes Online**, membuka
   bagian **Integrasi CBT ke SPMB (opsional)**, lalu memilih
   **Tes Penerimaan yang Dihubungkan**. Master tes harus aktif dari unit yang sama.
   Jika tes hanya berlaku untuk prodi tertentu, paket CBT juga harus dibatasi pada
   prodi yang sesuai. Pemetaan tidak bisa diganti setelah ada attempt.
2. Buat penugasan dan biarkan peserta mengerjakan ujian. Koreksi esai terlebih
   dahulu bila ada. Hanya attempt **graded** yang dapat diproses.
3. Buka **Tes Online → Hasil Tes Online** lalu klik **Sahkan ke Hasil Tes**.
   Pilih pendaftaran peserta yang memiliki tes tersebut pada konfigurasi yang
   berlaku saat ia mendaftar. Sistem membatasi opsi pada pendaftaran aktif,
   disubmit, berada di tahap **tests**, unit benar, dan hasil masih pending.
4. Konfirmasikan nilai. Sistem mengunci data, menolak nilai yang sudah final,
   mencatat pemetaan attempt ↔ hasil tes secara unik, dan memanggil mekanisme
   hasil tes SPMB yang sudah digunakan oleh fitur impor XLSX/manual.
5. Jika seluruh tes wajib telah lengkap, mekanisme SPMB dapat memindahkan
   tahap **tests** → **selection** dan menghitung agregasi skor. **Keputusan
   diterima/ditolak tetap melalui mekanisme seleksi yang sudah ada**.

Nilai CBT berskala 0–100. Untuk master tes bertipe score, persentase disalin
ke score, dan pass/fail mengikuti ambang kelulusan di konfigurasi pendaftaran
yang dipin (jika tersedia). Untuk jenis pass_fail, hanya status pass/fail yang
disalin. Nilai, kelulusan, dan sumber transfer dicatat dalam audit; butir soal,
jawaban peserta, dan kunci tidak pernah disalin ke hasil pendaftaran.

## Pengamanan
- Staf wajib memiliki akses paket/unit dan permission record_result_admissiontestresult.
- Pendaftaran wajib milik akun peserta yang mengerjakan attempt, unit dan prodi
  konsisten, dan tes memang terdaftar pada versi konfigurasi pendaftar.
- Hasil tes yang telah selesai/dinilai atau telah menerima transfer tidak ditimpa.
- Satu attempt hanya bisa disahkan sekali; satu hasil penerimaan hanya bisa
  menerima satu attempt. Aksi dibatasi transaksi dan unique index pada DB.
- Assignment yang dicabut dan attempt yang menunggu koreksi tidak dapat ditransfer.
- Tidak ada bulk transfer diam-diam, perubahan pembayaran, atau keputusan seleksi.
- Pemetaan baru tidak mengubah paket legacy yang tidak dikaitkan ke seleksi.
- Tidak ada persyaratan memiliki atau menyelesaikan assignment CBT untuk mendaftar,
  melanjutkan tahap SPMB, atau menerima keputusan seleksi; fitur ini mandiri.

## Release gate (wajib dibuktikan pada staging, bukan hanya CI)
- [ ] Backup database + private storage, uji restore dan rencana rollback.
- [ ] Migrasi baru selesai tanpa kehilangan data CBT/seleksi lama.
- [ ] UAT admin lintas unit, prodi, jalur, gelombang, serta pendaftar multi-registrasi.
- [ ] Nilai esai pending dan assignment tercabut ditolak.
- [ ] Rekam hasil pertama berhasil, replay dan hasil yang sudah final ditolak.
- [ ] Uji peserta tanpa penugasan CBT bisa melanjutkan alur pendaftaran/seleksi normal.
- [ ] Uji paket CBT mandiri dan paket tertaut yang belum disahkan tidak mengubah hasil SPMB.
- [ ] Uji kelengkapan Tes Penerimaan yang memang dikonfigurasi wajib, tahap lanjut, kalkulasi skor dan keputusan tetap manual.
- [ ] Uji pengawas dan ujian serentak ratusan peserta, dua tab, koneksi lambat, mobile.
- [ ] Scheduler setiap menit dan queue notification workers aktif pada server nyata.
- [ ] Catat operator, build SHA, tanggal, bukti UAT, dan keputusan GO/NO-GO.

**CI otomatis menguji kode dan migrasi terisolasi; CI bukan bukti deployment production.**
