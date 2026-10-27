<x-filament-panels::page>
    <div class="space-y-5">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Paket Tes Online hanya muncul jika ditugaskan oleh pengelola.
            Modul ini mandiri dan tidak otomatis mengubah tahapan atau hasil seleksi pendaftaran.
        </p>

        @forelse ($assignments as $assignment)
            @php
                $exam = $assignment->exam;
                $latest = $assignment->attempts->first();
                $mayStart = ($latest?->status === 'in_progress' && $latest->expires_at->isFuture())
                    || ($exam->isOpen() && $assignment->attempts->count() < $exam->max_attempts);
            @endphp
            <x-filament::section :heading="$exam->title" :description="$exam->unit->name">
                <div class="space-y-3">
                    @if ($exam->description)
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $exam->description }}</p>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <x-filament::badge color="info">{{ $exam->duration_minutes }} menit</x-filament::badge>
                        <x-filament::badge color="gray">Maksimal {{ $exam->max_attempts }} percobaan</x-filament::badge>
                        @if ($latest)
                            <x-filament::badge :color="$latest->status === 'graded' ? 'success' : 'warning'">
                                {{ $latest->status === 'graded' ? 'Dinilai' : ($latest->status === 'pending_review' ? 'Menunggu koreksi' : ($latest->status === 'in_progress' ? 'Sedang dikerjakan' : 'Selesai')) }}
                            </x-filament::badge>
                        @endif
                    </div>
                    @if ($latest?->status === 'graded')
                        <p class="text-sm font-semibold">
                            Skor akhir: {{ number_format((float) $latest->percentage, 2, ',', '.') }}%
                            · {{ $latest->passed ? 'Lulus' : 'Belum lulus' }}
                        </p>
                    @endif

                    @if ($mayStart)
                        <x-filament::button wire:click="startExam('{{ $assignment->uuid }}')" wire:loading.attr="disabled">
                            {{ $latest?->status === 'in_progress' && $latest->expires_at->isFuture() ? 'Lanjutkan Ujian' : 'Mulai Ujian' }}
                        </x-filament::button>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Ujian belum dibuka, sudah ditutup, atau batas pengerjaan sudah tercapai.
                        </p>
                    @endif
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Belum ada Tes Online yang ditugaskan kepada akun Anda.
                </p>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
