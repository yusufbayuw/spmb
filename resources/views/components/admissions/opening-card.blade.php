@props(['opening'])

@php
    $status = $opening->operationalStatus();
    $isOpen = $status === 'open';
    $isScheduled = $status === 'scheduled';
    $statusLabel = match ($status) {
        'open' => 'Dibuka',
        'scheduled' => 'Akan Dibuka',
        'closed' => 'Ditutup',
        default => $opening->statusLabel(),
    };
    $statusClasses = match ($status) {
        'open' => 'bg-emerald-100 text-emerald-800',
        'scheduled' => 'bg-blue-100 text-blue-800',
        'closed' => 'bg-slate-200 text-slate-700',
        default => 'bg-slate-100 text-slate-700',
    };
    $dateLabel = $isScheduled ? 'Pendaftaran dibuka' : ($isOpen ? 'Batas pendaftaran' : 'Pendaftaran ditutup');
    $dateValue = $isScheduled ? $opening->opened_at : $opening->closed_at;
    $title = $opening->studyProgram?->label() ?? $opening->unit?->name;
    $fee = (float) $opening->registration_fee === 0.0 ? 'Gratis' : $opening->formattedFee();
@endphp

<article class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            @if ($opening->studyProgram)
                <p class="text-xs font-bold uppercase tracking-wide text-blue-700">{{ $opening->unit?->name }}</p>
            @endif
            <h3 class="mt-1 text-xl font-extrabold tracking-tight text-slate-950">{{ $title }}</h3>
        </div>
        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClasses }}">{{ $statusLabel }}</span>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
        <div>
            <dt class="text-xs font-semibold text-slate-500">Tahun {{ $opening->unit?->isHigherEducation() ? 'Akademik' : 'Ajaran' }}</dt>
            <dd class="mt-1 text-sm font-bold text-slate-900">{{ $opening->academic_year }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-slate-500">Gelombang</dt>
            <dd class="mt-1 text-sm font-bold text-slate-900">{{ $opening->wave }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-slate-500">{{ $dateLabel }}</dt>
            <dd class="mt-1 text-sm font-bold text-slate-900">
                {{ $dateValue ? $dateValue->format('d F Y · H:i') . ' WIB' : 'Mengikuti informasi unit' }}
            </dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-slate-500">Biaya pendaftaran</dt>
            <dd class="mt-1 text-sm font-bold text-slate-900">{{ $fee }}</dd>
        </div>
    </dl>

    <div class="mt-auto flex flex-col gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('admissions.show', $opening) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
            Lihat Detail
        </a>
        @if ($isOpen)
            <a href="{{ route('admissions.apply', $opening) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                Daftar →
            </a>
        @endif
    </div>
</article>
