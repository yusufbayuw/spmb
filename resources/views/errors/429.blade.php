@include('errors.layout', [
    'status' => 429,
    'title' => 'Terlalu banyak percobaan',
    'message' => 'Permintaan dari perangkat Anda sedang dibatasi sementara untuk menjaga keamanan dan kestabilan sistem.',
    'hint' => 'Tunggu beberapa saat sebelum mencoba kembali. Hindari menekan tombol kirim atau masuk berulang kali.',
    'primaryLabel' => request()->is('admin*') ? 'Kembali ke Panel' : (request()->is('pendaftar*') ? 'Kembali ke Dashboard' : 'Kembali ke Beranda'),
])
