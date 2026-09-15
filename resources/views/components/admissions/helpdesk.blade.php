@props(['unit' => null])

@php
    $portal = config('spmb.portal', []);
    $contactName = $unit?->public_contact_name ?: ($unit?->name ? 'Panitia Penerimaan '.$unit->name : ($portal['foundation_name'] ?? 'Yayasan Taruna Bakti'));
    $email = $unit?->public_email ?: ($portal['foundation_email'] ?? null);
    $phone = $unit?->public_phone ?: ($portal['foundation_phone'] ?? null);
    $whatsapp = $unit?->public_whatsapp ?: ($portal['foundation_whatsapp'] ?? null);
    $serviceHours = $unit?->public_service_hours ?: ($portal['service_hours'] ?? null);
    $address = $unit?->public_address ?: ($portal['foundation_address'] ?? null);
    $website = $unit?->public_website_url ?: ($portal['foundation_website'] ?? null);
    $whatsappDigits = filled($whatsapp) ? preg_replace('/\D+/', '', (string) $whatsapp) : null;
    $whatsappMessage = 'Halo, saya ingin bertanya mengenai '.($unit?->name ? 'penerimaan '.$unit->name : 'penerimaan Taruna Bakti').'.';
    $hasContact = filled($email) || filled($phone) || filled($whatsappDigits) || filled($serviceHours) || filled($address) || filled($website);
@endphp

@if ($hasContact)
    <div {{ $attributes->class(['rounded-2xl border border-slate-200 bg-white p-6']) }}>
        <p class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Butuh bantuan?</p>
        <h3 class="mt-2 text-lg font-extrabold text-slate-950">{{ $contactName }}</h3>

        <dl class="mt-5 grid gap-4 text-sm">
            @if ($serviceHours)
                <div>
                    <dt class="font-semibold text-slate-500">Jam layanan</dt>
                    <dd class="mt-1 text-slate-800">{{ $serviceHours }}</dd>
                </div>
            @endif
            @if ($address)
                <div>
                    <dt class="font-semibold text-slate-500">Alamat</dt>
                    <dd class="mt-1 leading-6 text-slate-800">{{ $address }}</dd>
                </div>
            @endif
            @if ($email)
                <div>
                    <dt class="font-semibold text-slate-500">Email</dt>
                    <dd class="mt-1"><a href="mailto:{{ $email }}" class="font-semibold text-blue-700 hover:text-blue-800">{{ $email }}</a></dd>
                </div>
            @endif
            @if ($phone)
                <div>
                    <dt class="font-semibold text-slate-500">Telepon</dt>
                    <dd class="mt-1"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="font-semibold text-blue-700 hover:text-blue-800">{{ $phone }}</a></dd>
                </div>
            @endif
        </dl>

        @if ($whatsappDigits || $website)
            <div class="mt-5 flex flex-wrap gap-2">
                @if ($whatsappDigits)
                    <a href="https://wa.me/{{ $whatsappDigits }}?text={{ rawurlencode($whatsappMessage) }}" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800">
                        Hubungi via WhatsApp
                    </a>
                @endif
                @if ($website)
                    <a href="{{ $website }}" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Website
                    </a>
                @endif
            </div>
        @endif
    </div>
@endif
