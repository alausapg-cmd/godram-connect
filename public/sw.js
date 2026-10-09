// GODRAM Connect service worker: keeps the app shell and the offline page
// available, caches built assets and archive images, never caches private pages.
const VERSION = 'godram-v4';
const SHELL = ['/offline', '/manifest.webmanifest', '/icons/icon-192.png', '/images/godram-logo.webp'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Static, fingerprinted or archival files: cache first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(VERSION).then((cache) => cache.put(request, copy));
                }
                return response;
            }))
        );
        return;
    }

    // Pages: always go to the network; fall back to the offline page.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline')));
    }
});

// Push notifications: show the notice, and open the right page when it is tapped.
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: 'GODRAM CONNECT', body: event.data?.text() }; }
    event.waitUntil(self.registration.showNotification(data.title || 'GODRAM CONNECT', {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        badge: data.badge || '/icons/icon-192.png',
        tag: data.tag,
        renotify: !!data.tag,
        data: { url: data.url || '/notifications' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/notifications', self.location.origin);
    if (target.origin !== self.location.origin) return;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const open = windows.find((w) => new URL(w.url).origin === target.origin);
        if (open) return open.focus().then(() => open.navigate(target.href));
        return self.clients.openWindow(target.href);
    }));
});
