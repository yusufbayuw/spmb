# Master Wilayah Indonesia

Dataset ini memuat master Provinsi, Kabupaten/Kota, Kecamatan, dan Desa/Kelurahan seluruh Indonesia untuk cascading select SPMB.

## Acuan resmi

Keputusan Menteri Dalam Negeri Nomor **300.2.2-2430 Tahun 2025** tentang Pemberian dan Pemutakhiran Kode, Data Wilayah Administrasi Pemerintahan, dan Pulau.

Portal resmi:
https://ditjenbinaadwil.kemendagri.go.id/peraturan/keputusan-menteri-dalam-negeri-300.2.2-2430-2025-228

## Sumber data terstruktur

Data CSV di direktori ini diturunkan dari https://github.com/cahyadsn/wilayah

- source commit: `654844912a876133c981b8b0cac09af7876f2a14`
- source file: `db/wilayah.sql`
- source blob: `182a51a9cf48935bedc1adca6d110d5659453cc1`
- lisensi sumber: MIT

Komentar header file SQL sumber masih menyebut Kepmendagri 300.2.2-2138 Tahun 2025, tetapi isi pada commit tersebut telah diperbarui. Cakupan datanya diverifikasi terhadap rekap Kepmendagri 300.2.2-2430 Tahun 2025.

## Cakupan tervalidasi

- Provinsi: **38**
- Kabupaten/Kota: **514** (416 kabupaten + 98 kota)
- Kecamatan: **7.285**
- Desa/Kelurahan: **83.762**
  - Kelurahan: **8.496**
  - Desa + Desa Adat: **75.266**

Data dipecah satu CSV per provinsi supaya ukuran file tetap wajar untuk Git dan review. Semua shard memakai header yang sama.
