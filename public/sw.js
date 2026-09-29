/* SPMB service worker: installability + push only.
 * Authenticated SPMB pages and documents are deliberately never cached.
 */
self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data?.json() ?? {};
    } catch {
        payload = { body: event.data?.text() ?? '' };
    }

    const data = payload.data && typeof payload.data === 'object' ? payload.data : {};

    event.waitUntil(self.registration.showNotification(payload.title || 'SPMB', {
        body: payload.body || 'Ada pembaruan pada proses SPMB.',
        icon: payload.icon || '/images/pwa/icon-192.png',
        badge: payload.badge || '/images/pwa/badge-96.png',
        image: payload.image,
        tag: payload.tag,
        renotify: payload.renotify === true,
        requireInteraction: payload.requireInteraction === true,
        actions: Array.isArray(payload.actions) ? payload.actions : [],
        vibrate: Array.isArray(payload.vibrate) ? payload.vibrate : [150, 80, 150],
        data,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    let target = new URL('/', self.location.origin);

    try {
        const requested = new URL(event.notification.data?.url || '/', self.location.origin);
        if (requested.origin === self.location.origin) {
            target = requested;
        }
    } catch {
        // Keep the safe same-origin default.
    }

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true,
        });

        for (const client of windows) {
            if ('navigate' in client) {
                await client.navigate(target.href);
            }

            if ('focus' in client) {
                return client.focus();
            }
        }

        return self.clients.openWindow(target.href);
    })());
});
