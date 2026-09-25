<div class="space-y-3">
    @php
        $hasFiles = $documents->isNotEmpty();
        $hasRejected = $documents->contains(fn ($document) => filled($document->rejection_reason));
        $allVerified = $hasFiles && $documents->every(fn ($document) => $document->is_verified);
    @endphp

    <div class="flex items-center gap-2 text-sm">
        @if (! $hasFiles)
            <x-heroicon-m-exclamation-circle class="h-5 w-5 text-warning-500" />
            <span class="font-medium text-warning-700 dark:text-warning-400">Belum diunggah</span>
        @elseif ($hasRejected)
            <x-heroicon-m-exclamation-triangle class="h-5 w-5 text-danger-500" />
            <span class="font-medium text-danger-700 dark:text-danger-400">Perlu diperbaiki</span>
        @elseif ($allVerified)
            <x-heroicon-m-check-circle class="h-5 w-5 text-success-500" />
            <span class="font-medium text-success-700 dark:text-success-400">Terverifikasi</span>
        @else
            <x-heroicon-m-clock class="h-5 w-5 text-warning-500" />
            <span class="font-medium text-gray-700 dark:text-gray-300">Sudah diunggah · menunggu verifikasi</span>
        @endif
    </div>

    @if ($hasFiles)
        <div class="grid gap-2 md:grid-cols-2">
            @foreach ($documents as $document)
                <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                    <div class="flex items-start justify-between gap-3">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $document->displayFileName() }}</span>
                        @if ($document->is_verified)
                            <x-filament::badge color="success">Terverifikasi</x-filament::badge>
                        @elseif ($document->rejection_reason)
                            <x-filament::badge color="danger">Ditolak</x-filament::badge>
                        @else
                            <x-filament::badge color="warning">Diperiksa</x-filament::badge>
                        @endif
                    </div>

                    @if ($document->rejection_reason)
                        <p class="mt-2 text-danger-600 dark:text-danger-400">
                            Alasan penolakan: {{ $document->rejection_reason }}
                        </p>
                    @endif

                    <a
                        href="{{ route('files.applicant.documents.show', $document) }}"
                        class="mt-2 inline-flex items-center gap-1 text-primary-600 hover:underline dark:text-primary-400"
                        @if($document->file_type !== 'docx')
                            data-file-preview
                            data-file-name="{{ $document->displayFileName() }}"
                        @endif
                    >
                        <x-heroicon-m-eye class="h-4 w-4" />
                        Lihat file
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
