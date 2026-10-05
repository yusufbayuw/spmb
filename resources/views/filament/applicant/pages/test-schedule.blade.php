<x-filament-panels::page>
    @php
        $progress = $this->scheduleProgress();
    @endphp

    <div
        class="space-y-6"
        x-data="{ scheduleConfirmed: @entangle('scheduleConfirmed') }"
        x-on:beforeunload.window="
            if (! scheduleConfirmed) {
                $event.preventDefault();
                $event.returnValue = '';
            }
        "
        x-on:click.window.capture="
            if (scheduleConfirmed) {
                return;
            }

            const link = $event.target.closest('a[href]');

            if (! link || link.target === '_blank' || link.hasAttribute('download') || link.dataset.allowTestScheduleNavigation === 'true') {
                return;
            }

            const href = link.getAttribute('href');

            if (! href || href.startsWith('#') || href.startsWith('javascript:')) {
                return;
            }

            $event.preventDefault();
            $event.stopImmediatePropagation();
            $dispatch('open-modal', { id: 'incomplete-test-schedule' });
        "
    >
        <p>{{ $registrationRecord->full_name }} · {{ $registrationRecord->registration_number }}</p>

        <x-filament::section heading="Kemajuan Pemilihan Jadwal">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-medium">
                        {{ $progress['booked'] }} dari {{ $progress['required'] }} tes wajib sudah dipilih
                    </p>

                    @if($scheduleConfirmed)
                        <x-filament::badge color="success">Sudah dikonfirmasi</x-filament::badge>
                    @elseif($progress['complete'])
                        <x-filament::badge color="warning">Menunggu konfirmasi</x-filament::badge>
                    @else
                        <x-filament::badge color="warning">{{ $progress['missing'] }} belum dipilih</x-filament::badge>
                    @endif
                </div>

                <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div
                        class="h-full rounded-full bg-primary-600 transition-all"
                        style="width: {{ $progress['required'] > 0 ? min(100, round(($progress['booked'] / $progress['required']) * 100)) : 0 }}%"
                    ></div>
                </div>

                @if(! $scheduleConfirmed)
                    <p class="text-sm text-warning-700 dark:text-warning-400">
                        Jangan tutup atau tinggalkan halaman ini sebelum seluruh tes wajib dipilih dan jadwal dikonfirmasi.
                    </p>
                @endif
            </div>
        </x-filament::section>

        @error('session')
            <p class="text-danger-600">{{ $message }}</p>
        @enderror

        @error('tests')
            <p class="text-danger-600">{{ $message }}</p>
        @enderror

        @foreach($registrationRecord->configuredTests() as $test)
            @php
                $booking = $this->booking($test['id']);
            @endphp

            <x-filament::section :heading="$test['name']">
                <div class="mb-6 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge :color="$test['is_required'] ? 'danger' : 'gray'">
                            {{ $test['is_required'] ? 'Wajib' : 'Opsional' }}
                        </x-filament::badge>

                        @if($booking?->session)
                            <x-filament::badge color="success" icon="heroicon-m-check-circle">
                                Jadwal sudah dipilih
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="warning">
                                Belum memilih sesi
                            </x-filament::badge>
                        @endif
                    </div>

                    @if($booking?->session)
                        <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Pilihan saat ini
                            </p>
                            <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $booking->session->starts_at->format('d/m/Y') }}
                                · {{ $booking->session->starts_at->format('H:i') }}–{{ $booking->session->ends_at->format('H:i') }}
                                · {{ $booking->session->location }}
                            </p>
                        </div>
                    @endif
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    @forelse($this->sessions($test['id']) as $session)
                        @php
                            $isSelected = $booking?->test_session_id === $session->id;
                            $remainingSeats = max(0, $session->capacity - $session->bookings_count);
                            $isFull = $remainingSeats <= 0;
                        @endphp

                        <div
                            @class([
                                'flex min-h-full flex-col rounded-2xl border p-5 shadow-sm transition sm:p-6',
                                'border-primary-500/50 bg-primary-50/60 ring-1 ring-primary-500/20 dark:bg-primary-950/20' => $isSelected,
                                'border-gray-200 bg-white dark:border-white/10 dark:bg-white/5' => ! $isSelected,
                            ])
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                                        {{ $session->starts_at->format('d/m/Y') }}
                                    </p>
                                    <p class="mt-1 text-lg font-bold leading-7 text-gray-950 dark:text-white">
                                        {{ $session->starts_at->format('H:i') }}–{{ $session->ends_at->format('H:i') }}
                                    </p>
                                </div>

                                @if($isSelected)
                                    <x-filament::badge color="primary" icon="heroicon-m-check">
                                        Dipilih
                                    </x-filament::badge>
                                @elseif($isFull)
                                    <x-filament::badge color="danger">
                                        Penuh
                                    </x-filament::badge>
                                @else
                                    <x-filament::badge color="success">
                                        Tersedia
                                    </x-filament::badge>
                                @endif
                            </div>

                            <div class="mt-5 space-y-3">
                                <div class="flex items-start gap-3">
                                    <x-filament::icon
                                        icon="heroicon-m-map-pin"
                                        class="mt-0.5 h-5 w-5 shrink-0 text-gray-400"
                                    />
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Lokasi</p>
                                        <p class="mt-0.5 break-words text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $session->location }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3">
                                    <x-filament::icon
                                        icon="heroicon-m-user-group"
                                        class="mt-0.5 h-5 w-5 shrink-0 text-gray-400"
                                    />
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Kuota</p>
                                        <p class="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                                            Sisa kuota: {{ $remainingSeats }} dari {{ $session->capacity }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 rounded-xl bg-gray-50 px-4 py-3 dark:bg-black/20">
                                <div class="flex items-start gap-3">
                                    <x-filament::icon
                                        icon="heroicon-m-clock"
                                        class="mt-0.5 h-5 w-5 shrink-0 text-gray-400"
                                    />
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                            Batas memilih / mengubah sesi
                                        </p>
                                        <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $session->booking_closes_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            @if(filled($session->instructions))
                                <div class="mt-4 border-l-2 border-gray-200 pl-3 dark:border-gray-700">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Petunjuk</p>
                                    <p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-300">
                                        {{ $session->instructions }}
                                    </p>
                                </div>
                            @endif

                            <div class="mt-auto pt-6">
                                <x-filament::button
                                    class="w-full justify-center sm:w-auto"
                                    wire:click="choose('{{ $session->uuid }}')"
                                    :color="$isSelected ? 'gray' : 'primary'"
                                    :icon="$isSelected ? 'heroicon-m-check' : null"
                                    :disabled="$isSelected || $isFull"
                                >
                                    @if($isSelected)
                                        Jadwal Dipilih
                                    @elseif($isFull)
                                        Sesi Penuh
                                    @else
                                        Pilih Sesi
                                    @endif
                                </x-filament::button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center dark:border-gray-700 md:col-span-2">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                Belum ada sesi yang dapat dipesan. Hubungi TU unit.
                            </p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        @endforeach

        <x-filament::section heading="Finalisasi Jadwal">
            <div class="space-y-3">
                @if($scheduleConfirmed)
                    <p class="text-sm text-success-700 dark:text-success-400">
                        Seluruh jadwal tes wajib sudah dipilih dan dikonfirmasi.
                    </p>
                @elseif($progress['complete'])
                    <p class="text-sm">
                        Semua tes wajib sudah memiliki jadwal. Periksa sekali lagi, lalu konfirmasikan agar pemilihan jadwal dinyatakan selesai.
                    </p>

                    <x-filament::modal
                        id="confirm-test-schedule"
                        width="lg"
                        :close-by-clicking-away="false"
                    >
                        <x-slot name="trigger">
                            <x-filament::button>
                                Konfirmasi Semua Jadwal Tes
                            </x-filament::button>
                        </x-slot>

                        <x-slot name="heading">
                            Konfirmasi Semua Jadwal Tes
                        </x-slot>

                        <x-slot name="description">
                            Pastikan seluruh jadwal berikut sudah sesuai sebelum dikonfirmasi.
                        </x-slot>

                        <div class="space-y-3">
                            @foreach(collect($registrationRecord->configuredTests())->where('is_required', true) as $requiredTest)
                                @php
                                    $requiredBooking = $this->booking($requiredTest['id']);
                                @endphp

                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="font-medium">{{ $requiredTest['name'] }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        {{ $requiredBooking?->session?->label() ?? 'Belum memilih sesi' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>

                        <x-slot name="footer">
                            <div class="flex justify-end">
                                <x-filament::button wire:click="confirmSchedule">
                                    Ya, Konfirmasi Jadwal
                                </x-filament::button>
                            </div>
                        </x-slot>
                    </x-filament::modal>
                @else
                    <p class="text-sm text-warning-700 dark:text-warning-400">
                        Masih ada {{ $progress['missing'] }} tes wajib yang belum memiliki jadwal.
                    </p>

                    <x-filament::button disabled>
                        Konfirmasi Semua Jadwal Tes
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>

        <div class="flex flex-wrap items-center gap-3">
            @if($this->canPrintTestCard())
                <x-filament::button
                    tag="a"
                    :href="route('registration.test-card', $registrationRecord)"
                    target="_blank"
                >
                    Cetak Kartu Tes
                </x-filament::button>
            @elseif($progress['complete'])
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Kartu tes dapat dicetak setelah seluruh jadwal tes wajib dikonfirmasi.
                </p>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Kartu tes dapat dicetak setelah seluruh tes wajib memiliki sesi dan jadwal dikonfirmasi.
                </p>
            @endif

            <x-filament::button
                tag="a"
                :href="\App\Filament\Applicant\Pages\RegistrationStatus::getUrl(['registration' => $registrationRecord->uuid])"
                color="gray"
            >
                Kembali ke Status
            </x-filament::button>
        </div>

        @if(! $scheduleConfirmed)
            <x-filament::modal
                id="incomplete-test-schedule"
                width="lg"
                :close-button="false"
                :close-by-clicking-away="false"
                :close-by-escaping="false"
            >
                <x-slot name="heading">
                    Pemilihan Jadwal Belum Selesai
                </x-slot>

                <x-slot name="description">
                    Selesaikan seluruh jadwal tes wajib sebelum meninggalkan halaman ini.
                </x-slot>

                @if($progress['complete'])
                    <p>
                        Seluruh tes wajib sudah dipilih, tetapi jadwal belum dikonfirmasi. Tekan
                        <strong>Konfirmasi Semua Jadwal Tes</strong> untuk menyelesaikan proses.
                    </p>
                @else
                    <p>
                        Anda baru memilih {{ $progress['booked'] }} dari {{ $progress['required'] }} tes wajib.
                        Masih ada {{ $progress['missing'] }} tes wajib yang belum memiliki jadwal.
                    </p>
                @endif

                <x-slot name="footer">
                    <div class="flex justify-end">
                        <x-filament::button
                            x-on:click="$dispatch('close-modal', { id: 'incomplete-test-schedule' })"
                        >
                            Lanjut Pilih Jadwal
                        </x-filament::button>
                    </div>
                </x-slot>
            </x-filament::modal>
        @endif
    </div>
</x-filament-panels::page>
