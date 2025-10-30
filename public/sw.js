// public/sw.js

// -------------------------
// Versión / cache stamp
// -------------------------
const SW_VERSION = '1.0.0';
const STATIC_CACHE = 'static-v' + SW_VERSION;

// anti-spam para compose
let lastComposeAt = 0;
const COMPOSE_COOLDOWN_MS = 1500;

// -------------------------
// INSTALL
// -------------------------
self.addEventListener('install', (event) => {
    self.skipWaiting();

    // Ejemplo cache inicial (lo podés habilitar más adelante):
    // event.waitUntil(
    //   caches.open(STATIC_CACHE).then(cache => {
    //     return cache.addAll([
    //       '/',
    //       '/offline',
    //       '/css/app.css',
    //       '/js/nav.js',
    //       '/js/pwa.js',
    //       '/icons/pwa-192.png'
    //     ]);
    //   })
    // );
});

// -------------------------
// ACTIVATE
// -------------------------
self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        await self.clients.claim();

        // limpiar caches viejos
        const names = await caches.keys();
        await Promise.all(
            names
                .filter(name => name.startsWith('static-v') && name !== STATIC_CACHE)
                .map(name => caches.delete(name))
        );
    })());
});

// -------------------------
// PUSH
// -------------------------
self.addEventListener('push', (event) => {
    event.waitUntil((async () => {
        const now = Date.now();
        if (now - lastComposeAt < COMPOSE_COOLDOWN_MS) {
            return self.registration.showNotification('🎲 La Taberna', {
                body: 'Novedades frescas. Abrí la app.',
                icon: '/icons/pwa-192.png',
                badge: '/icons/pwa-192.png',
                data: { url: '/' }
            });
        }
        lastComposeAt = now;

        try {
            const res = await fetch('/push/compose-notification', {
                credentials: 'include',
            });

            if (!res.ok) {
                throw new Error('compose HTTP ' + res.status);
            }

            let data;
            try {
                data = await res.json();
            } catch (jsonErr) {
                throw new Error('compose invalid JSON');
            }

            const title = data && data.title ? String(data.title) : 'Notificación';
            const body = data && data.body ? String(data.body) : '';
            const icon = data && data.icon ? String(data.icon) : '/icons/pwa-192.png';
            const badge = data && data.badge ? String(data.badge) : icon;
            const url = (data && data.url) ? String(data.url) : '/';

            await self.registration.showNotification(title, {
                body,
                icon,
                badge,
                data: { url }
            });

        } catch (err) {
            await self.registration.showNotification('🎲 La Taberna', {
                body: 'Hay mesas nuevas y cupos disponibles.',
                icon: '/icons/pwa-192.png',
                badge: '/icons/pwa-192.png',
                data: { url: '/' }
            });
        }
    })());
});

// -------------------------
// notificationclick
// -------------------------
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil((async () => {
        const absoluteUrl = new URL(targetUrl, self.location.origin).href;

        const clientList = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        });

        let bestClient = null;

        for (const client of clientList) {
            try {
                if (client.url === absoluteUrl) {
                    bestClient = client;
                    break;
                }
                if (!bestClient && client.url.startsWith(self.location.origin)) {
                    bestClient = client;
                }
            } catch (err) { }
        }

        if (bestClient) {
            if (bestClient.url === absoluteUrl) {
                return bestClient.focus();
            }

            if ('navigate' in bestClient) {
                await bestClient.navigate(absoluteUrl);
                return bestClient.focus();
            }

            return self.clients.openWindow(absoluteUrl);
        }

        return self.clients.openWindow(absoluteUrl);
    })());
});

// -------------------------
// fetch (opcional offline futuro)
// -------------------------
// self.addEventListener('fetch', (event) => {
//   if (event.request.method === 'GET' &&
//       event.request.headers.get('accept')?.includes('text/html')) {
//     event.respondWith((async () => {
//       try {
//         const res = await fetch(event.request);
//         return res;
//       } catch (_) {
//         const cache = await caches.open(STATIC_CACHE);
//         const offline = await cache.match('/offline');
//         return offline || new Response('Offline', { status: 503 });
//       }
//     })());
//   }
// });
