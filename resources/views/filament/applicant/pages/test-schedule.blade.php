<x-filament-panels::page>
    <p>{{ $registrationRecord->full_name }} · {{ $registrationRecord->registration_number }}</p>
    @error('session')<p class="text-danger-600">{{ $message }}</p>@enderror
    @foreach($registrationRecord->configuredTests() as $test)
        @php
$booking = $this->booking($test['id']);
@endphp
        <x-filament::section :heading="$test['name']">
            <p class="mb-4 text-sm">{{ $test['is_required'] ? 'Wajib' : 'Opsional' }} · Pilihan saat ini: {{ $booking?->session?->label() ?? 'Belum memilih sesi' }}</p>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse($this->sessions($test['id']) as $session)
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="font-semibold">{{ $session->label() }}</p>
                        <p class="text-sm">Sisa kuota: {{ max(0, $session->capacity - $session->bookings_count) }} · Pemesanan sampai {{ $session->booking_closes_at->format('d/m/Y H:i') }}</p>
                        <p class="my-3 text-sm">{{ $session->instructions }}</p>
                        <x-filament::button wire:click="choose('{{ $session->uuid }}')" :disabled="$booking?->test_session_id === $session->id || $session->bookings_count >= $session->capacity">{{ $booking?->test_session_id === $session->id ? 'Dipilih' : 'Pilih Sesi' }}</x-filament::button>
                    </div>
                @empty
                    <p>Belum ada sesi yang dapat dipesan. Hubungi TU unit.</p>
                @endforelse
            </div>
        </x-filament::section>
    @endforeach
    <div class="flex flex-wrap gap-3">
        @if($this->hasSelectedSession())
            <x-filament::button tag="a" :href="route('registration.test-card', $registrationRecord)" target="_blank">Cetak Kartu Tes</x-filament::button>
        @endif
        <x-filament::button tag="a" :href="\App\Filament\Applicant\Pages\RegistrationStatus::getUrl(['registration' => $registrationRecord->uuid])" color="gray">Kembali ke Status</x-filament::button>
    </div>
</x-filament-panels::page>
