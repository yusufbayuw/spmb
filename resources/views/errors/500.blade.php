@include('errors.layout', [
    'status' => 500,
    'title' => 'Terjadi gangguan pada sistem',
    'message' => 'Permintaan Anda belum dapat diproses karena terjadi gangguan internal. Silakan kembali ke aplikasi dan coba lagi.',
    'hint' => 'Jika masalah yang sama terus terjadi, sampaikan kode referensi di bawah kepada panitia atau administrator agar dapat ditelusuri.',
    'primaryLabel' => request()->is('admin*') ? 'Kembali ke Panel' : (request()->is('pendaftar*') ? 'Kembali ke Dashboard' : 'Kembali ke Beranda'),
    'showReference' => true,
])
