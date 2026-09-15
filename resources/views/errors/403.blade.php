@include('errors.layout', [
    'status' => 403,
    'title' => 'Akses tidak diizinkan',
    'message' => request()->is('pendaftar*')
        ? 'Anda tidak memiliki akses ke halaman ini. Halaman tersebut mungkin bukan bagian dari pendaftaran Anda atau belum tersedia pada tahap proses saat ini.'
        : 'Akun Anda tidak memiliki izin untuk membuka halaman atau menjalankan tindakan ini.',
    'hint' => request()->is('admin*')
        ? 'Jika akses ini diperlukan untuk pekerjaan Anda, hubungi administrator untuk memeriksa hak akses akun.'
        : null,
    'primaryLabel' => request()->is('admin*') ? 'Kembali ke Panel' : 'Kembali ke Dashboard',
])
