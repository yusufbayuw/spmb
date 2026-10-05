<x-filament-panels::page>
    @php
        $progress = $this->scheduleProgress();
    @endphp

    <div
        x-data="{ scheduleConfirmed: @entangle('scheduleConfirmed') }"
        x-on:beforeunload.window="
            if (! scheduleConfirmed) {
                $event.preventDefault();
                $event.returnValue = '';
            }
        "
        x-on:click.capture="
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
                <p class="mb-4 text-sm">
                    {{ $test['is_required'] ? 'Wajib' : 'Opsional' }}
                    · Pilihan saat ini: {{ $booking?->session?->label() ?? 'Belum memilih sesi' }}
                </p>

                <div class="grid gap-4 md:grid-cols-2">
                    @forelse($this->sessions($test['id']) as $session)
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p class="font-semibold">{{ $session->label() }}</p>
                            <p class="text-sm">
                                Sisa kuota: {{ max(0, $session->capacity - $session->bookings_count) }}
                                · Pemesanan sampai {{ $session->booking_closes_at->format('d/m/Y H:i') }}
                            </p>
                            <p class="my-3 text-sm">{{ $session->instructions }}</p>

                            <x-filament::button
                                wire:click="choose('{{ $session->uuid }}')"
                                :disabled="$booking?->test_session_id === $session->id || $session->bookings_count >= $session->capacity"
                            >
                                {{ $booking?->test_session_id === $session->id ? 'Dipilih' : 'Pilih Sesi' }}
                            </x-filament::button>
                        </div>
                    @empty
                        <p>Belum ada sesi yang dapat dipesan. Hubungi TU unit.</p>
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
