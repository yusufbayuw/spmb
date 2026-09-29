<x-guest-layout>
    <div class="space-y-6">
        <div class="text-center">
            <h1 class="text-2xl font-semibold text-gray-900">Masuk ke SPMB Taruna Bakti</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600">
                Satu halaman masuk untuk pendaftar, TU, Admin Unit, dan Super Admin.
                Portal akan dipilih otomatis berdasarkan hak akses akun.
            </p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="email" value="Email atau Username" />
                <x-text-input
                    id="email"
                    class="mt-1 block w-full"
                    type="text"
                    name="email"
                    :value="old('email')"
                    required
                    autofocus
                    autocomplete="username"
                />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" value="Password" />
                <x-text-input
                    id="password"
                    class="mt-1 block w-full"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between gap-3">
                    <x-input-label for="captcha" value="Kode Keamanan" />
                    <button
                        type="button"
                        id="refresh-login-captcha"
                        class="text-xs font-medium text-blue-600 hover:text-blue-700 hover:underline"
                    >
                        Muat kode baru
                    </button>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <img
                        id="login-captcha-image"
                        src="{{ $captchaImages['light'] }}"
                        alt="Kode keamanan"
                        class="block h-auto max-w-full"
                    >
                </div>

                <x-text-input
                    id="captcha"
                    class="mt-3 block w-full"
                    type="text"
                    name="captcha"
                    required
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    placeholder="Ketik 5 karakter"
                />
                <p class="mt-1 text-xs text-gray-500">Huruf besar/kecil tidak dibedakan.</p>
                <x-input-error :messages="$errors->get('captcha')" class="mt-2" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input
                        id="remember_me"
                        type="checkbox"
                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                        name="remember"
                        value="1"
                    >
                    <span class="ms-2 text-sm text-gray-600">Ingat saya</span>
                </label>

                <a
                    class="text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline"
                    href="{{ route('password.request') }}"
                >
                    Lupa password?
                </a>
            </div>

            <x-primary-button class="w-full justify-center">
                Masuk
            </x-primary-button>
        </form>

        <div class="border-t border-gray-200 pt-5 text-center text-sm text-gray-600">
            Belum memiliki akun pendaftar?
            <a href="{{ route('register') }}" class="font-medium text-blue-600 hover:text-blue-700 hover:underline">
                Buat akun
            </a>
        </div>
    </div>

    <script>
        (() => {
            const button = document.getElementById('refresh-login-captcha');
            const image = document.getElementById('login-captcha-image');
            const input = document.getElementById('captcha');

            if (! button || ! image || ! input) {
                return;
            }

            button.addEventListener('click', async () => {
                button.disabled = true;

                try {
                    const response = await fetch(@js(route('login.captcha')), {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    });

                    if (! response.ok) {
                        throw new Error('Captcha refresh failed.');
                    }

                    const payload = await response.json();
                    image.src = payload.light;
                    input.value = '';
                    input.focus();
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
</x-guest-layout>
