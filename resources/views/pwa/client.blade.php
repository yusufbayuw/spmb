@if ($showBanner)
    <aside id="spmb-pwa-onboarding" hidden aria-live="polite">
        <button type="button" class="spmb-pwa-onboarding__close" data-pwa-dismiss aria-label="Tutup">&times;</button>

        <div class="spmb-pwa-onboarding__content">
            <strong>Gunakan SPMB sebagai aplikasi</strong>
            <span data-pwa-status>Memeriksa dukungan perangkat…</span>
        </div>

        <div class="spmb-pwa-onboarding__actions">
            <button type="button" data-pwa-install-button hidden>Install aplikasi</button>
            <button type="button" data-pwa-enable-button hidden>Aktifkan notifikasi</button>
            <button type="button" data-pwa-disable-button hidden>Nonaktifkan notifikasi</button>
        </div>
    </aside>
@endif

@php
    $pushConfigured = filled(config('webpush.vapid.public_key'))
        && filled(config('webpush.vapid.private_key'));

    $pwaConfig = [
        'authenticated' => auth()->check(),
        'pushConfigured' => $pushConfigured,
        'vapidUrl' => route('push.vapid-public-key'),
        'subscribeUrl' => route('push.subscriptions.store'),
        'unsubscribeUrl' => route('push.subscriptions.destroy'),
        'serviceWorkerUrl' => asset('sw.js'),
        'appName' => 'SPMB Taruna Bakti',
    ];
@endphp

<script id="spmb-pwa-config" type="application/json">
    @json($pwaConfig)
</script>
<script src="{{ asset('js/pwa.js') }}" defer></script>

<style>
    #spmb-pwa-onboarding {
        position: fixed;
        z-index: 100;
        right: 1rem;
        bottom: 1rem;
        width: min(26rem, calc(100vw - 2rem));
        padding: 1rem;
        border: 1px solid rgb(191 219 254);
        border-radius: .9rem;
        background: rgb(255 255 255 / .98);
        box-shadow: 0 18px 45px rgb(15 23 42 / .2);
        color: rgb(15 23 42);
    }

    .dark #spmb-pwa-onboarding {
        border-color: rgb(51 65 85);
        background: rgb(15 23 42 / .98);
        color: rgb(248 250 252);
    }

    .spmb-pwa-onboarding__close {
        position: absolute;
        top: .35rem;
        right: .55rem;
        padding: .2rem .4rem;
        color: rgb(100 116 139);
        font-size: 1.4rem;
        line-height: 1;
    }

    .spmb-pwa-onboarding__content {
        display: grid;
        gap: .25rem;
        padding-right: 1.5rem;
    }

    .spmb-pwa-onboarding__content span {
        color: rgb(71 85 105);
        font-size: .875rem;
    }

    .dark .spmb-pwa-onboarding__content span {
        color: rgb(203 213 225);
    }

    .spmb-pwa-onboarding__actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .85rem;
    }

    .spmb-pwa-onboarding__actions button {
        padding: .55rem .8rem;
        border-radius: .55rem;
        background: rgb(37 99 235);
        color: white;
        font-size: .8rem;
        font-weight: 600;
    }

    .spmb-pwa-onboarding__actions button[data-pwa-disable-button] {
        border: 1px solid rgb(203 213 225);
        background: white;
        color: rgb(51 65 85);
    }
</style>
