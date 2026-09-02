<x-filament-panels::page>
    @php($payment = $this->registrationRecord->latestPayment)

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Virtual Account</x-slot>
            <x-slot name="description">Gunakan nomor dan nominal berikut saat melakukan pembayaran.</x-slot>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Nomor VA</div>
                    <div class="mt-1 font-mono text-xl font-semibold text-gray-950 dark:text-white">{{ $payment->va_number }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Nominal</div>
                    <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</div>
                </div>
            </div>

            @if ($payment->rejection_reason)
                <div class="mt-4 rounded-xl bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                    <strong>Bukti sebelumnya ditolak:</strong> {{ $payment->rejection_reason }}
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Bukti Pembayaran</x-slot>
            @if ($this->registrationRecord->current_stage === 'payment_verification')
                <div class="flex items-center gap-3 rounded-xl bg-warning-50 p-4 dark:bg-warning-500/10">
                    <x-heroicon-o-clock class="h-6 w-6 text-warning-600" />
                    <div>
                        <div class="font-semibold text-gray-950 dark:text-white">Sedang diverifikasi</div>
                        <div class="text-sm text-gray-600 dark:text-gray-300">Bukti pembayaran sudah diterima dan sedang diperiksa Tata Usaha.</div>
                    </div>
                </div>
            @else
                <form wire:submit="submit" class="space-y-6">
                    {{ $this->form }}
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <x-filament::button
                            tag="a"
                            href="{{ \App\Filament\Applicant\Pages\RegistrationStatus::getUrl(['registration' => $this->registrationRecord->id]) }}"
                            color="gray"
                            outlined
                        >
                            Kembali
                        </x-filament::button>
                        <x-filament::button type="submit" icon="heroicon-m-arrow-up-tray">
                            Kirim Bukti Pembayaran
                        </x-filament::button>
                    </div>
                </form>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
