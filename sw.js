/**
 * Youth Employment Bridge - Service Worker para Notificaciones Push (Root Scope)
 */

let storedDeviceToken = '';
let lastSwNotificationId = 0;

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let data = {
        title: 'Youth Employment Bridge',
        body: 'Tienes una nueva notificación.',
        icon: './public/assets/icons/notification-icon.png',
        badge: './public/assets/icons/notification-badge.png',
        url: './?action=alerts',
        tag: 'yeb-push-notification'
    };

    if (event.data) {
        try {
            const parsed = event.data.json();
            data = Object.assign(data, parsed);
        } catch (e) {
            data.body = event.data.text() || data.body;
        }
    }

    const options = {
        body: data.body || data.message,
        icon: data.icon,
        badge: data.badge,
        tag: data.tag || 'yeb-notification-' + Date.now(),
        renotify: true,
        data: {
            url: data.url || './?action=alerts',
            timestamp: Date.now()
        },
        actions: [
            { action: 'open', title: 'Ver ahora' },
            { action: 'dismiss', title: 'Cerrar' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Youth Employment Bridge', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'dismiss') {
        return;
    }

    const targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : './?action=alerts';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.focus();
                    if ('navigate' in client && targetUrl) {
                        return client.navigate(targetUrl);
                    }
                    return;
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

self.addEventListener('message', (event) => {
    if (!event.data) return;

    if (event.data.type === 'SET_DEVICE_TOKEN') {
        storedDeviceToken = event.data.deviceToken || '';
    }

    if (event.data.type === 'SHOW_NOTIFICATION') {
        const payload = event.data.payload || {};
        const options = {
            body: payload.body || payload.message,
            icon: payload.icon || './public/assets/icons/notification-icon.png',
            badge: payload.badge || './public/assets/icons/notification-badge.png',
            tag: payload.tag || 'yeb-app-push-' + Date.now(),
            renotify: true,
            data: {
                url: payload.url || './?action=alerts'
            }
        };

        event.waitUntil(
            self.registration.showNotification(payload.title || 'Youth Employment Bridge', options)
        );
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag === 'yeb-bg-sync' && storedDeviceToken) {
        event.waitUntil(checkBackgroundPush());
    }
});

self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'yeb-periodic-sync' && storedDeviceToken) {
        event.waitUntil(checkBackgroundPush());
    }
});

async function checkBackgroundPush() {
    if (!storedDeviceToken) return;
    try {
        const res = await fetch(`?action=push_poll&device_token=${encodeURIComponent(storedDeviceToken)}&last_id=${lastSwNotificationId}&_=${Date.now()}`);
        if (!res.ok) return;
        const data = await res.json();
        if (data && data.has_new && Array.isArray(data.notifications)) {
            for (const notif of data.notifications) {
                const notifId = parseInt(notif.id, 10) || 0;
                if (notifId > lastSwNotificationId) {
                    lastSwNotificationId = notifId;
                }
                await self.registration.showNotification(notif.title || 'Youth Employment Bridge', {
                    body: notif.message,
                    icon: './public/assets/icons/notification-icon.png',
                    badge: './public/assets/icons/notification-badge.png',
                    tag: 'yeb-push-' + notifId,
                    renotify: true,
                    data: { url: notif.url || './?action=alerts' }
                });
            }
        }
    } catch (e) {}
}
