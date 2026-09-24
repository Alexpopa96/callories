self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};

    event.waitUntil(self.registration.showNotification(data.title || 'Kalo Mind', {
        body: data.body || '',
        icon: '/images/kalo-mind-icon-192.png',
        badge: '/images/kalo-mind-icon-192.png',
        data: {url: data.url || '/today'},
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/today';

    event.waitUntil(self.clients.matchAll({type: 'window', includeUncontrolled: true}).then((windows) => {
        for (const client of windows) {
            if ('focus' in client) {
                client.navigate(url);
                return client.focus();
            }
        }
        return self.clients.openWindow(url);
    }));
});
