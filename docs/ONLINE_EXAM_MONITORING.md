# Dashboard Pengawas Tes Online — monitoring berkala

**Lokasi:** Admin → Tes Online → Hasil Tes Online → Dashboard Pengawas.

## Cara kerja
- Pilih **satu paket Tes Online**. Admin Unit hanya melihat paket unitnya; Super Admin melihat paket dari semua unit operasional.
- Halaman mengambil data ulang **setiap 15 detik** lewat Livewire polling. Tombol Segarkan Sekarang tersedia.
- Ringkasan berdasarkan penugasan dan **percobaan terakhir** setiap akun: belum mulai, sedang mengerjakan, melewati deadline (scheduler belum final), perlu koreksi esai, selesai, akses dicabut.
- Pencarian nama/email dan filter status serta 25 peserta per halaman; angka ringkasan selalu seluruh penugasan untuk paket yang dipilih.
- Rincian menampilkan percobaan terakhir, jumlah soal yang tersimpan jawabannya, waktu tersisa berdasarkan deadline server, serta waktu mulai/selesai.
- Panel tidak menampilkan soal, jawaban, kunci koreksi, atau skor mentah dan tidak melakukan perubahan status saat refresh.

## Makna status dan keterbatasan
- **Sedang Mengerjakan** = ada percobaan tersimpan \`in_progress\` dengan \`expires_at > now()\`. **Bukan** bukti browser terhubung atau peserta sedang aktif mengetik.
- **Melewati Batas Waktu** = \`in_progress\` tetapi deadline telah lewat; scheduler \`spmb:finalize-online-exams\` belum menyelesaikannya.
- **Selesai** = status \`submitted\`, \`graded\`, atau \`expired\` tersimpan. **Perlu Koreksi** (\`pending_review\`) terpisah.
- **Akses Dicabut** mengalahkan status attempt; penugasan yang dicabut tidak tampil sebagai aktif.
- **Terjawab** = \`answered_at\` terisi. Untuk jawaban yang dikosongkan ulang, kolom ini dapat tetap terisi. Tidak dimaksudkan sebagai proktor/peringatan kecurangan.
- Tidak memakai WebSocket, kehadiran browser, deteksi pindah tab, atau pengawasan kamera. Tidak ada aksi paksa submit atau perpanjang waktu.
- Angka berdasarkan pengambilan data server terakhir, bukan push waktu nyata.

## Release gates
- [ ] CI full regression, MySQL (query aggregate latest attempt), dan cek Livewire.
- [ ] UAT staging dengan berbagai unit, ratusan peserta, alur timeout dan koreksi esai.
- [ ] Uji akses panel pada admin unit berbeda dan akun pendaftar.
- [ ] Monitoring ketepatan refresh, pagination dan beban SQL sebelum penerapan produksi.
- [ ] Backup, restore, dan rollback staging.

Dashboard memantau percobaan Tes Online mandiri. Tidak mengubah workflow, hasil seleksi SPMB/PMB, atau pembayaran.
