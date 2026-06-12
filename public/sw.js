self.addEventListener('push', (event) => {
    const fallback = {
        title: 'Service status changed',
        body: 'A monitored service has a new status update.',
        url: '/',
    };
    const payload = event.data ? event.data.json() : fallback;
    const title = payload.title || fallback.title;
    const options = {
        body: payload.body || fallback.body,
        data: {
            url: payload.url || fallback.url,
        },
        icon: '/images/icon-192.png',
        badge: '/images/icon-192.png',
        tag: payload.tag || 'statuspage-alert',
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    const url = event.notification.data?.url || '/';

    event.notification.close();
    event.waitUntil((async () => {
        const windows = await clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            if (new URL(client.url).pathname === new URL(url, self.location.origin).pathname) {
                await client.focus();
                return;
            }
        }

        await clients.openWindow(url);
    })());
});
