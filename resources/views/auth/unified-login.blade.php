<x-filament-panels::layout.simple>
    <div class="fi-simple-page">
        <section class="grid auto-cols-fr gap-y-6">
            <x-filament-panels::header.simple
                :heading="'Masuk ke '.config('spmb.portal.name', 'SPMB')"
                :logo="true"
                :subheading="null"
            />

            <form method="POST" action="{{ route('login.store', absolute: false) }}" class="grid gap-y-6">
                @csrf

                <x-filament-forms::field-wrapper
                    id="email"
                    label="Email atau Username"
                    state-path="email"
                    required
                >
                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="email"
                            type="text"
                            name="email"
                            :value="old('email')"
                            autocomplete="username"
                            autofocus
                            required
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament-forms::field-wrapper
                    id="password"
                    label="Kata sandi"
                    state-path="password"
                    required
                >
                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        />
                    </x-filament::input.wrapper>

                    <div class="mt-2 text-end">
                        <a
                            href="{{ route('password.request', absolute: false) }}"
                            class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                        >
                            Lupa kata sandi?
                        </a>
                    </div>
                </x-filament-forms::field-wrapper>

                <label class="flex items-center gap-x-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <x-filament::input.checkbox
                        name="remember"
                        value="1"
                        :checked="old('remember') === '1'"
                    />
                    <span>Ingat saya</span>
                </label>

                <x-filament-forms::field-wrapper
                    id="captcha"
                    label="Kode Keamanan"
                    state-path="captcha"
                    helper-text="Masukkan 5 karakter pada gambar. Huruf besar/kecil tidak dibedakan."
                    required
                >
                    <div class="flex items-center gap-3">
                        <div class="overflow-hidden rounded-lg bg-gray-950">
                            <img
                                id="unified-login-captcha-image"
                                src="{{ $captchaImages['light'] }}"
                                alt="Kode keamanan"
                                class="block h-auto max-w-full"
                            >
                        </div>

                        <x-filament::icon-button
                            id="refresh-unified-login-captcha"
                            type="button"
                            icon="heroicon-m-arrow-path"
                            color="gray"
                            tooltip="Muat kode baru"
                        />
                    </div>

                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="captcha"
                            type="text"
                            name="captcha"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            required
                        />
                    </x-filament::input.wrapper>
                </x-filament-forms::field-wrapper>

                <x-filament::button type="submit" class="w-full">
                    Masuk
                </x-filament::button>
            </form>

            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                atau
                <a
                    href="{{ route('register', absolute: false) }}"
                    class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    buat akun baru
                </a>
            </p>
        </section>
    </div>

    <script>
        (() => {
            const button = document.getElementById('refresh-unified-login-captcha');
            const image = document.getElementById('unified-login-captcha-image');
            const input = document.getElementById('captcha');

            if (! button || ! image || ! input) {
                return;
            }

            button.addEventListener('click', async () => {
                button.disabled = true;

                try {
                    const response = await fetch(@js(route('login.captcha', absolute: false)), {
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
</x-filament-panels::layout.simple>
