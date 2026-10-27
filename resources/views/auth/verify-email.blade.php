<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Terima kasih telah membuat akun. Sebelum melanjutkan pendaftaran, buka email verifikasi yang telah
        kami kirim dan klik tombol <strong>Verifikasi Alamat Email</strong>.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600" role="status">
            Tautan verifikasi baru telah dikirim. Silakan periksa email Anda.
        </div>
    @endif

    <x-auth.email-verification-help />

    <div class="mt-4 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>Kirim Ulang Email Verifikasi</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Keluar
            </button>
        </form>
    </div>
</x-guest-layout>
