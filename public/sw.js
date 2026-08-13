/* global self, clients */
self.addEventListener('push', (event) => {
    let data = {
        title: 'Aura & Oath',
        body: 'You have a new update.',
        url: '/',
        tag: 'aura-notification',
    };

    try {
        if (event.data) {
            data = { ...data, ...event.data.json() };
        }
    } catch (e) {
        try {
            data.body = event.data ? event.data.text() : data.body;
        } catch (err) {}
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Aura & Oath', {
            body: data.body || '',
            icon: '/images/placeholders/product.svg',
            badge: '/images/placeholders/product.svg',
            tag: data.tag || 'aura-notification',
            data: { url: data.url || '/' },
            renotify: true,
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if ('focus' in client) {
                    if (typeof client.navigate === 'function') {
                        try {
                            client.navigate(target);
                        } catch (e) {}
                    }
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(target);
            }
        })
    );
});
