@props([
    'unit',
    'contained' => true,
])

@php
    $items = $unit?->preRegistrationItems() ?? [];
    $heading = $unit?->pre_registration_heading ?: 'Siapkan informasi utama';
    $body = $unit?->pre_registration_body;
    $hasContent = filled($heading) || filled($body) || $items !== [];
@endphp

@if ($hasContent)
    @if ($contained)
        <section class="border-t border-slate-200 bg-white py-14 sm:py-16">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
    @endif

    <div>
        <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Sebelum mendaftar</p>
        <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">{{ $heading }}</h2>

        @if (filled($body))
            <div class="cms-content mt-4">{!! $body !!}</div>
        @endif

        @if ($items !== [])
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach ($items as $item)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="font-bold text-slate-900">{{ $item['title'] }}</p>
                        @if (filled($item['description'] ?? null))
                            <p class="mt-1 text-sm leading-6 text-slate-600">{{ $item['description'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($contained)
            </div>
        </section>
    @endif
@endif
