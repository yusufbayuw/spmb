@include('errors.layout', [
    'status' => 419,
    'title' => 'Sesi Anda telah berakhir',
    'message' => 'Demi keamanan, sesi yang terlalu lama tidak aktif akan ditutup otomatis. Silakan masuk kembali lalu ulangi tindakan terakhir Anda.',
    'hint' => 'Jika Anda sedang mengisi formulir, periksa kembali data terakhir setelah masuk kembali sebelum melanjutkan.',
    'primaryUrl' => request()->is('admin*') ? url('/admin/login') : url('/pendaftar/login'),
    'primaryLabel' => 'Masuk kembali',
    'secondaryUrl' => url('/'),
    'secondaryLabel' => 'Halaman utama',
])
