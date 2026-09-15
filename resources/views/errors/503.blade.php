@include('errors.layout', [
    'status' => 503,
    'title' => 'Sistem sedang tidak tersedia',
    'message' => 'SPMB sedang menjalani pemeliharaan atau mengalami gangguan sementara. Layanan akan dapat digunakan kembali setelah proses selesai.',
    'hint' => 'Silakan coba kembali beberapa saat lagi. Hindari mengirim formulir berulang kali selama layanan belum normal.',
    'primaryUrl' => url('/'),
    'primaryLabel' => 'Kembali ke Halaman Utama',
    'secondaryUrl' => null,
    'secondaryLabel' => null,
])
