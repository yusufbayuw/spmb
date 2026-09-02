@php
    $steps = \App\Models\Registration::STAGES;
    $keys = array_keys($steps);
    $currentIndex = array_search($record->current_stage, $keys, true);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;
@endphp

<div class="space-y-6">
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Nomor Registrasi</div>
            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $record->registration_number }}</div>
        </div>
        <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Tahap Saat Ini</div>
            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $record->stageLabel() }}</div>
        </div>
    </div>

    @if ($record->data_validation_status === 'revision' && $record->data_validation_notes)
        <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
            <div class="font-semibold">Data perlu diperbaiki</div>
            <div class="mt-1">{{ $record->data_validation_notes }}</div>
        </div>
    @endif

    <div>
        <div class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Progres Pendaftaran</div>
        <div class="space-y-2">
            @foreach ($steps as $key => $label)
                @php
                    $index = array_search($key, $keys, true);
                    $done = $index < $currentIndex || $record->current_stage === 'completed';
                    $active = $key === $record->current_stage;
                @endphp
                <div class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $done ? 'bg-success-500 text-white' : ($active ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/10') }}">
                        {{ $done ? '✓' : $loop->iteration }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $label }}</div>
                        @if ($active)
                            <div class="text-xs text-primary-600 dark:text-primary-400">Tahap sedang berjalan</div>
                        @elseif ($done)
                            <div class="text-xs text-success-600 dark:text-success-400">Selesai</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($record->latestPayment)
        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Pembayaran</div>
            <div class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                <div><span class="text-gray-500">VA:</span> {{ $record->latestPayment->va_number ?? '-' }}</div>
                <div><span class="text-gray-500">Status:</span> {{ str($record->latestPayment->status)->replace('_', ' ')->title() }}</div>
            </div>
        </div>
    @endif

    @if ($record->selection)
        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Hasil Seleksi</div>
            <div class="mt-1 text-sm">{{ str($record->selection->decision)->replace('_', ' ')->title() }}</div>
        </div>
    @endif

    @if ($record->announcement?->status === 'published')
        <div class="rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
            <div class="text-sm font-semibold text-success-800 dark:text-success-200">{{ $record->announcement->title }}</div>
            @if ($record->announcement->message)
                <div class="mt-1 text-sm text-success-700 dark:text-success-300">{{ $record->announcement->message }}</div>
            @endif
        </div>
    @endif
</div>
