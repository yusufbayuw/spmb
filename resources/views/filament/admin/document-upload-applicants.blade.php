@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $applicants */
    $applicants = $this->uploadApplicants();
    $overview = app(\App\Services\DocumentUploadOverview::class);
@endphp

<div class="space-y-4">
    <x-filament::input.wrapper>
        <x-filament::input
            type="search"
            wire:model.live.debounce.350ms="uploadSearch"
            placeholder="Cari nama pendaftar atau nomor registrasi"
            aria-label="Cari pendaftar yang sudah mengunggah"
        />
    </x-filament::input.wrapper>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-3 font-medium">Pendaftar</th>
                    <th class="px-3 py-3 font-medium">Unit</th>
                    <th class="px-3 py-3 font-medium">Kategori Upload</th>
                    <th class="px-3 py-3 font-medium">Status Lampiran</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($applicants as $registration)
                    @php($summary = $overview->summary($registration))
                    <tr wire:key="upload-applicant-{{ $registration->id }}">
                        <td class="px-3 py-3">
                            <a
                                class="font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                href="{{ \App\Filament\Admin\Resources\DocumentResource::getUrl('index', ['registrationId' => $registration->id]) }}"
                            >{{ $registration->full_name }}</a>
                            <div class="text-xs text-gray-500">{{ $registration->registration_number ?: 'Tanpa nomor registrasi' }}</div>
                        </td>
                        <td class="px-3 py-3">{{ $registration->unit?->name }}</td>
                        <td class="px-3 py-3 font-semibold tabular-nums">{{ $summary['uploaded'] }}/{{ $summary['total'] }}</td>
                        <td class="px-3 py-3 text-xs">
                            <span class="text-success-600">{{ $summary['verified'] }} terverifikasi</span>
                            &middot; <span>{{ $summary['pending'] }} menunggu</span>
                            @if ($summary['rejected'])
                                &middot; <span class="text-danger-600">{{ $summary['rejected'] }} ditolak</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-5 text-center text-gray-500">Tidak ada pendaftar yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-gray-500">
            {{ number_format($applicants->total(), 0, ',', '.') }} pendaftar
            &middot; halaman {{ $applicants->currentPage() }} dari {{ max(1, $applicants->lastPage()) }}
        </p>
        <div class="flex gap-2">
            <x-filament::button size="sm" color="gray" wire:click="previousUploadPage" :disabled="$applicants->onFirstPage()">Sebelumnya</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="nextUploadPage" :disabled="! $applicants->hasMorePages()">Berikutnya</x-filament::button>
        </div>
    </div>
</div>
