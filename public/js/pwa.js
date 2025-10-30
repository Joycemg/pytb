// public/js/pwa.js
(async function () {
    // Guardas DUROS de entorno mínimo ---------------------------------
    if (
        !('serviceWorker' in navigator) ||
        !('PushManager' in window) ||
        !('Notification' in window) ||
        !window.PWA ||
        !window.PWA.vapidPublicKey ||
        !window.PWA.subscribeUrl
    ) {
        return;
    }

    try {
        // 1) Registrar el Service Worker si no está
        const reg = await navigator.serviceWorker.register('/sw.js');

        // 2) Esperar a que el SW controle la página
        let activeReg = reg.active ? reg : null;
        if (!activeReg) activeReg = await navigator.serviceWorker.ready;

        // 3) Permiso de notificaciones (respetar 'denied')
        if (Notification.permission === 'denied') return;

        let permission = Notification.permission;
        if (permission !== 'granted') permission = await Notification.requestPermission();
        if (permission !== 'granted') return;

        // 4) Helper VAPID
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = atob(base64);
            const out = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; i++) out[i] = rawData.charCodeAt(i);
            return out;
        }

        // 5) Obtener/crear suscripción
        const applicationServerKey = urlBase64ToUint8Array(window.PWA.vapidPublicKey);
        const ONE_DAY_MS = 24 * 60 * 60 * 1000;
        const now = Date.now();

        async function createSubscription() {
            try {
                return await activeReg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey,
                });
            } catch (subscribeErr) {
                // Algunos navegadores lanzan InvalidStateError cuando ya existe una
                // suscripción antigua con otra VAPID key. La eliminamos y reintentamos.
                if (subscribeErr && subscribeErr.name === 'InvalidStateError') {
                    try {
                        const existing = await activeReg.pushManager.getSubscription();
                        if (existing) await existing.unsubscribe();
                    } catch (unsubscribeErr) {
                        console.warn('[PWA] unsubscribe error:', unsubscribeErr);
                    }
                    return await activeReg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey,
                    });
                }
                throw subscribeErr;
            }
        }

        let sub = await activeReg.pushManager.getSubscription();
        if (sub && typeof sub.expirationTime === 'number') {
            const expiresIn = sub.expirationTime - now;
            if (Number.isFinite(expiresIn) && expiresIn <= ONE_DAY_MS) {
                try {
                    await sub.unsubscribe();
                } catch (unsubscribeErr) {
                    console.warn('[PWA] unsubscribe expiring sub error:', unsubscribeErr);
                }
                sub = null;
            }
        }

        if (!sub) {
            sub = await createSubscription();
        }

        // 6) Normalizar payload para backend
        const jsonSub = sub.toJSON();
        const payload = {
            endpoint: sub.endpoint,
            keys: {
                p256dh: jsonSub.keys.p256dh,
                auth: jsonSub.keys.auth,
            },
        };

        // 7) Enviar al backend (con CSRF si existe)
        const csrf = document.querySelector('meta[name="csrf-token"]');
        const payloadJson = JSON.stringify(payload);
        const storageKey = 'pwa:last-subscription';
        let cachedPayload = null;
        try {
            cachedPayload = localStorage.getItem(storageKey);
        } catch (_) { }

        if (cachedPayload === payloadJson) {
            return;
        }

        const res = await fetch(window.PWA.subscribeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...(csrf ? { 'X-CSRF-TOKEN': csrf.content } : {}),
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        });

        if (!res.ok) {
            throw new Error('push subscribe HTTP ' + res.status);
        }

        try {
            localStorage.setItem(storageKey, payloadJson);
        } catch (_) { }

        // 8) (opcional) ping de prueba sólo para admins si definiste la ruta
        // if (window.PWA.pingUrl) {
        //   await fetch(window.PWA.pingUrl, {
        //     method: 'POST',
        //     headers: { ...(csrf ? { 'X-CSRF-TOKEN': csrf.content } : {}) },
        //     credentials: 'same-origin',
        //   });
        // }
    } catch (err) {
        console.warn('[PWA] init error:', err);
    }
})();
