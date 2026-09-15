@include('errors.layout', [
    'status' => 404,
    'title' => 'Halaman tidak ditemukan',
    'message' => 'Tautan yang Anda buka mungkin sudah tidak berlaku, alamatnya berubah, atau halaman tersebut memang tidak tersedia.',
    'hint' => 'Periksa kembali alamat halaman atau lanjutkan dari dashboard aplikasi.',
    'primaryLabel' => request()->is('admin*') ? 'Kembali ke Panel' : (request()->is('pendaftar*') ? 'Kembali ke Dashboard' : 'Kembali ke Beranda'),
])
