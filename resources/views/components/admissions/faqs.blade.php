@props([
    'faqs',
    'heading' => 'Pertanyaan yang sering diajukan',
    'contained' => true,
])

@if ($faqs->isNotEmpty())
    @if ($contained)
        <section class="border-t border-slate-200 bg-white py-14 sm:py-16">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
    @endif

    <div>
        <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">FAQ</p>
        <h2 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">{{ $heading }}</h2>
        <div class="mt-6 space-y-3">
            @foreach ($faqs as $faq)
                <details class="group rounded-xl border border-slate-200 bg-white p-5">
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4 font-bold text-slate-950">
                        <span>{{ $faq->question }}</span>
                        <span class="text-blue-700 transition group-open:rotate-45" aria-hidden="true">+</span>
                    </summary>
                    @if ($faq->contextLabel())
                        <p class="mt-3 text-xs font-semibold text-blue-700">{{ $faq->contextLabel() }}</p>
                    @endif
                    <div class="cms-content mt-3">{!! $faq->answer !!}</div>
                </details>
            @endforeach
        </div>
    </div>

    @if ($contained)
            </div>
        </section>
    @endif
@endif
