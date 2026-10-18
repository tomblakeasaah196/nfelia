/**
 * Nfelia service worker.
 *
 * 1. The app's own files (styles, scripts, fonts, icons, logos) are kept on
 *    the phone, so pages open fast and still look right with no network.
 * 2. Each app page someone opens is kept too, so with no network, or one too
 *    slow to answer, they can still read what they have seen. A kept copy
 *    carries <meta name="nf-cached" content="when it was saved"> and
 *    assets/js/shell.js says so on screen. Kept pages belong to whoever was
 *    signed in, so all of them are dropped at every sign-in and sign-out.
 * 3. Phone notifications: show them, and open the right page when tapped.
 *
 * Only pages the server marks as app pages are kept (views/layouts/app.php
 * sends X-Nfelia-Page). Never kept: anything but GET, /api, sign-in pages,
 * documents shared with customers (/doc) and downloads.
 */
var VERSION = '2026-10-22.1';
var FILES = 'nf-files-' + VERSION; // the app's own files, replaced with each version
var PAGES = 'nf-pages';            // pages someone opened, kept for offline reading
var SLOW_MS = 8000;                // after this long, show the kept copy instead of waiting
var MAX_PAGES = 60;
var PRECACHE = [
    'offline.html',
    'assets/fonts/plus-jakarta-sans-latin.woff2',
    'assets/fonts/bricolage-grotesque-latin.woff2',
    'android-chrome-192x192.png',
    'icons/badge-96.png'
];

var scope = self.registration.scope;
var scopePath = new URL(scope).pathname;

// The path inside the app: "invoices/view/4", "assets/css/app.css".
function rel(url) {
    return url.pathname.indexOf(scopePath) === 0 ? url.pathname.slice(scopePath.length) : url.pathname;
}

// The installed app starts at /dashboard?source=pwa. That and /dashboard are one page.
function pageKey(href) {
    var url = new URL(href);
    url.searchParams.delete('source');
    url.hash = '';
    return url.href;
}

self.addEventListener('install', function (event) {
    // One missing file must not stop the worker installing: notifications depend on it.
    event.waitUntil(caches.open(FILES).then(function (cache) {
        return Promise.all(PRECACHE.map(function (path) {
            return cache.add(new Request(new URL(path, scope).href, { cache: 'reload' })).catch(function () {});
        }));
    }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (event) {
    event.waitUntil(Promise.all([
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k.indexOf('nf-files-') === 0 && k !== FILES; })
                .map(function (k) { return caches.delete(k); }));
        }),
        self.registration.navigationPreload ? self.registration.navigationPreload.enable().catch(function () {}) : null
    ]).then(function () { return self.clients.claim(); }));
});

// A page posted a form a moment ago. The page it lands on must come from the
// server, never from the kept copy, or the result of the form would be hidden.
var lastPostAt = 0;

self.addEventListener('fetch', function (event) {
    var req = event.request;
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    var path = rel(url);

    // Signing in or out, or a session that has ended: what was kept belonged
    // to whoever was signed in before.
    var auth = /^(login|logout|signin|login-link|register)(\/|$)/.test(path);
    if (auth) event.waitUntil(caches.delete(PAGES));

    if (req.method !== 'GET') {
        if (req.mode === 'navigate') lastPostAt = Date.now();
        return;
    }
    if (req.mode === 'navigate') {
        // Every navigation is answered here, so the server sees exactly one
        // request for it: one-time sign-in links must never be sent twice.
        var skip = auth || /^(api|doc|cron|webhook)(\/|$)/.test(path) || /(export|download)/.test(path);
        event.respondWith(skip ? straight(event) : page(event));
        return;
    }
    if (req.headers.has('Range') || /^assets\/video\//.test(path)) return;
    if (/^(assets|icons|uploads\/logos)\//.test(path) || /^[\w.-]+\.(png|ico|svg|webmanifest)$/.test(path)) {
        event.respondWith(file(event));
    }
});

// Straight to the server, using the request the browser already started.
function straight(event) {
    return Promise.resolve(event.preloadResponse)
        .then(function (res) { return res || fetch(event.request); })
        .catch(function () { return offline(); });
}

// ---------- Pages: the network first, the kept copy when it fails or stalls ----------
function page(event) {
    var req = event.request;
    var key = pageKey(req.url);
    var keeping = Promise.resolve();
    var network = Promise.resolve(event.preloadResponse).then(function (res) { return res || fetch(req); })
        .then(function (res) {
            if (keepable(res)) keeping = keep(key, res.clone());
            return res;
        });
    // Let the copy finish saving even after the page has its answer.
    event.waitUntil(network.then(function () { return keeping; }).catch(function () {}));

    var mayWait = Date.now() - lastPostAt > 60000;
    return new Promise(function (resolve) {
        var answered = false;
        var answer = function (res) { if (!answered) { answered = true; resolve(res); } };
        network.then(answer, function () {
            caches.open(PAGES).then(function (c) { return c.match(key); })
                .then(function (kept) { return kept || offline(); })
                .then(answer, function () { answer(offline()); });
        });
        if (!mayWait) return;
        setTimeout(function () {
            if (answered) return;
            caches.open(PAGES).then(function (c) { return c.match(key); }).then(function (kept) {
                if (!kept || answered) return;
                answer(kept);
                // The network may still answer. If it does, the page can offer the newer copy.
                network.then(function (res) {
                    if (!keepable(res)) return;
                    return keeping.then(function () { return self.clients.get(event.resultingClientId); })
                        .then(function (client) { if (client) client.postMessage({ type: 'nf-fresh', url: key }); });
                }).catch(function () {});
            });
        }, SLOW_MS);
    });
}

function keepable(res) {
    return res && res.ok && res.type === 'basic' && res.headers.get('X-Nfelia-Page') === 'app';
}

// Save the page with a note of when, so the copy says how old it is.
function keep(key, res) {
    return res.text().then(function (html) {
        var stamp = '<meta name="nf-cached" content="' + new Date().toISOString() + '">';
        html = html.replace(/<head(\s[^>]*)?>/i, function (m) { return m + stamp; });
        var headers = new Headers(res.headers);
        headers.delete('Content-Length');
        return caches.open(PAGES).then(function (cache) {
            return cache.delete(key)
                .then(function () { return cache.put(key, new Response(html, { status: 200, headers: headers })); })
                .then(function () { return cache.keys(); })
                .then(function (keys) {
                    // Oldest first: keep the most recent MAX_PAGES.
                    return Promise.all(keys.slice(0, Math.max(0, keys.length - MAX_PAGES)).map(function (k) { return cache.delete(k); }));
                });
        });
    }).catch(function () {});
}

function offline() {
    return caches.match(new URL('offline.html', scope).href).then(function (res) {
        return res || new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>No connection</title><p style="font:16px system-ui;padding:24px">No connection. Pages you have opened before still work offline.</p>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    });
}

// ---------- The app's files: from the phone, refreshed in the background ----------
function file(event) {
    var req = event.request;
    var url = new URL(req.url);
    return caches.open(FILES).then(function (cache) {
        var refresh = function () {
            return fetch(req).then(function (res) {
                if (!res.ok || res.status !== 200 || res.type !== 'basic') return res;
                var copy = res.clone();
                // A new ?v= replaces the old copy of the same file.
                return cache.keys().then(function (keys) {
                    return Promise.all(keys.filter(function (k) {
                        var u = new URL(k.url);
                        return u.pathname === url.pathname && u.search !== url.search;
                    }).map(function (k) { return cache.delete(k); }));
                }).then(function () { return cache.put(req, copy); })
                  .catch(function () {})
                  .then(function () { return res; });
            });
        };
        return cache.match(req).then(function (hit) {
            if (hit) {
                // Versioned files never change; the rest are refreshed for next time.
                if (!url.searchParams.has('v')) event.waitUntil(refresh().catch(function () {}));
                return hit;
            }
            return refresh().catch(function () {
                // Offline with no copy of this version: any version beats a broken page.
                return cache.match(req, { ignoreSearch: true }).then(function (any) { return any || Response.error(); });
            });
        });
    });
}

// The page asks for the files it used before this worker was in charge.
self.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type !== 'nf-warm' || !Array.isArray(data.urls)) return;
    event.waitUntil(caches.open(FILES).then(function (cache) {
        return Promise.all(data.urls.slice(0, 30).map(function (href) {
            var url;
            try { url = new URL(href); } catch (e) { return null; }
            if (url.origin !== self.location.origin || !/^(assets|icons)\//.test(rel(url))) return null;
            return cache.match(url.href).then(function (hit) { return hit || cache.add(url.href); }).catch(function () {});
        }));
    }));
});

// ---------- Notifications ----------
// Every notification opens notifications/open/{id}: the server marks it read
// and sends on to the exact page, through sign-in first when needed
// (app/controllers/NotificationsController.php). Only ever a page of this site.
function safeUrl(path) {
    try {
        var url = new URL(path || 'notifications', scope);
        return url.origin === self.location.origin ? url.href : scope;
    } catch (e) {
        return scope;
    }
}

// Pages that are open hear about it, so the bell's number moves at once.
function tellPages(message) {
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
        list.forEach(function (c) { c.postMessage(message); });
    });
}

function appBadge(count) {
    if (!self.navigator || typeof count !== 'number') return Promise.resolve();
    var set = count > 0 ? self.navigator.setAppBadge && self.navigator.setAppBadge(count)
                        : self.navigator.clearAppBadge && self.navigator.clearAppBadge();
    return Promise.resolve(set).catch(function () {});
}

self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; }
    catch (e) { data = { title: 'Nfelia', body: event.data ? event.data.text() : '' }; }

    event.waitUntil(Promise.all([
        self.registration.showNotification(data.title || 'Nfelia', {
            body: data.body || '',
            icon: scope + 'android-chrome-192x192.png',
            badge: scope + 'icons/badge-96.png',
            tag: data.tag || undefined,
            renotify: !!data.tag,
            timestamp: Date.now(),
            data: { url: safeUrl(data.url), id: data.id || null }
        }),
        appBadge(data.badge),
        tellPages({ type: 'nf-notify', id: data.id || null, badge: data.badge })
    ]));
});

// Whether an open page has unsaved typing (assets/js/bell.js answers). A
// page that doesn't answer quickly counts as having none.
function hasUnsaved(client) {
    return new Promise(function (resolve) {
        if (typeof MessageChannel === 'undefined' || typeof client.postMessage !== 'function') return resolve(false);
        var channel = new MessageChannel();
        var timer = setTimeout(function () { resolve(false); }, 300);
        channel.port1.onmessage = function (e) { clearTimeout(timer); resolve(!!(e.data && e.data.dirty)); };
        try { client.postMessage({ type: 'nf-dirty?' }, [channel.port2]); } catch (err) { clearTimeout(timer); resolve(false); }
    });
}

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || scope;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
        // Nfelia is already open: bring it forward on this notification's page
        // instead of opening another window, unless something half typed
        // there would be lost (P18).
        for (var j = 0; j < list.length; j++) {
            if (new URL(list[j].url).origin === self.location.origin && 'navigate' in list[j]) {
                var client = list[j];
                return hasUnsaved(client).then(function (unsaved) {
                    if (unsaved) return self.clients.openWindow(url);
                    return client.focus()
                        .then(function (c) { return (c || client).navigate(url); })
                        .then(function (c) { return c || self.clients.openWindow(url); })
                        .catch(function () { return self.clients.openWindow(url); });
                });
            }
        }
        return self.clients.openWindow(url);
    }));
});

// The browser replaced this phone's subscription (they expire, or the push
// service rotates them). Save the new one in place of the old, so alerts
// keep arriving without anyone opening Nfelia first.
self.addEventListener('pushsubscriptionchange', function (event) {
    var old = event.oldSubscription;
    event.waitUntil(Promise.resolve(event.newSubscription || (old && old.options
        ? self.registration.pushManager.subscribe(old.options) : null)
    ).then(function (sub) {
        if (!sub || !old) return;
        return fetch(new URL('api/notifications?action=resubscribe', scope).href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ old_endpoint: old.endpoint, subscription: sub.toJSON() })
        });
    }).catch(function () {}));
});
