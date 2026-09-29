@php
    $consents = $user?->loadMissing('consents.policy')->consents?->sortByDesc('accepted_at') ?? collect();
@endphp

@if($consents->isEmpty())
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
        Belum ada riwayat persetujuan akun yang tercatat.
    </div>
@else
    <div class="space-y-3">
        @foreach($consents as $consent)
            <details class="rounded-xl border border-gray-200 dark:border-white/10">
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $consent->typeLabel() }}
                    · v{{ $consent->policy?->version ?? '-' }}
                    · {{ $consent->accepted_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                </summary>
                <div class="space-y-3 border-t border-gray-200 p-4 text-sm dark:border-white/10">
                    <div class="font-semibold text-gray-950 dark:text-white">{{ $consent->title_snapshot }}</div>
                    <div class="text-gray-700 dark:text-gray-200">{{ strip_tags($consent->confirmation_snapshot) }}</div>
                    <div class="break-all text-xs text-gray-500 dark:text-gray-400">Hash: {{ $consent->content_hash }}</div>
                    @if($consent->withdrawn_at)
                        <div class="text-xs font-medium text-warning-600">Dicabut: {{ $consent->withdrawn_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</div>
                    @endif
                </div>
            </details>
        @endforeach
    </div>
@endif
