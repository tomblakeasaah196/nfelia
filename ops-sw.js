/**
 * Nfelia Ops' service worker, for ops.nfelia.com only: phone alerts for the
 * Nfelia team (app/ops/alerts.php). It shows each alert, and a tap opens it
 * (alerts/open/{id}: marked read, then its page, through sign-in if needed).
 * Nothing is cached. The app's own sw.js serves nfelia.com.
 */
'use strict';
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });

function sameOrigin(url) {
    try { var u = new URL(url, self.location.origin); return u.origin === self.location.origin ? u.href : self.location.origin + '/alerts'; }
    catch (e) { return self.location.origin + '/alerts'; }
}

self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: 'Nfelia Ops', body: event.data ? event.data.text() : '' }; }
    var shown = self.registration.showNotification(data.title || 'Nfelia Ops', {
        body: data.body || '',
        icon: '/android-chrome-192x192.png',
        badge: '/icons/badge-96.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: sameOrigin(data.url || '/alerts') }
    });
    var badge = (typeof data.badge === 'number' && self.navigator && self.navigator.setAppBadge)
        ? (data.badge > 0 ? self.navigator.setAppBadge(data.badge) : self.navigator.clearAppBadge()).catch(function () {}) : Promise.resolve();
    event.waitUntil(Promise.all([shown, badge]));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || self.location.origin + '/alerts';
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
        for (var i = 0; i < list.length; i++) {
            if (new URL(list[i].url).origin === self.location.origin && 'navigate' in list[i]) {
                var client = list[i];
                return client.focus().then(function (c) { return (c || client).navigate(url); });
            }
        }
        return self.clients.openWindow(url);
    }));
});
