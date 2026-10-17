/**
 * Nfelia service worker.
 *
 * Today it does one job: show phone notifications (reminders, deliveries
 * due, new tasks) and open the right page when one is tapped.
 *
 * The PWA will add offline caching here. Until then there is deliberately
 * no fetch handler, so every request goes to the network exactly as it does
 * without a service worker.
 */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; }
    catch (e) { data = { title: 'Nfelia', body: event.data ? event.data.text() : '' }; }

    var scope = self.registration.scope;
    event.waitUntil(self.registration.showNotification(data.title || 'Nfelia', {
        body: data.body || '',
        icon: scope + 'android-chrome-192x192.png',
        badge: scope + 'favicon-32x32.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: new URL(data.url || 'tasks', scope).href }
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || self.registration.scope;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
        for (var i = 0; i < list.length; i++) {
            if (list[i].url === url && 'focus' in list[i]) return list[i].focus();
        }
        return self.clients.openWindow(url);
    }));
});
