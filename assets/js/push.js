/**
 * Nfelia — phone notifications (web push), shared by Tasks & Calendar now
 * and by the PWA later. Registers /sw.js, asks permission only when the
 * person taps "Turn on", and keeps the server's copy of the subscription
 * in step with the browser's.
 *
 *   NfeliaPush.supported()      can this browser do it at all?
 *   NfeliaPush.current()        the live subscription, or null
 *   NfeliaPush.enable(key)      ask, subscribe, save on the server
 *   NfeliaPush.disable()        unsubscribe here and on the server
 *   NfeliaPush.sync(key)        re-save an existing subscription (cheap),
 *                               or renew it if Nfelia's key has changed
 */
(function () {
    var base = (window.Nfelia && window.Nfelia.url) || location.origin;
    var scope = new URL(base + '/').pathname;

    function post(path, body) {
        return fetch(base + '/tasks/' + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).catch(function () {
            throw new Error('No connection. Check your data and try again.');
        }).then(function (r) {
            if (r.redirected) throw new Error('You were signed out. Reload the page to sign in again.');
            return r.json().catch(function () { return null; }).then(function (d) {
                if (!r.ok || !d || d.error) throw new Error((d && d.error) || 'Could not reach Nfelia. Try again.');
                return d;
            });
        });
    }

    function keyBytes(b64) {
        var pad = '='.repeat((4 - b64.length % 4) % 4);
        var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    function sameKey(sub, key) {
        var have = sub.options && sub.options.applicationServerKey;
        if (!have) return true;   // the browser does not say; assume it matches
        var a = new Uint8Array(have), b = keyBytes(key);
        if (a.length !== b.length) return false;
        for (var i = 0; i < a.length; i++) { if (a[i] !== b[i]) return false; }
        return true;
    }

    // A subscription made with Nfelia's current key: the one this phone has,
    // or a new one when there is none or the key has changed since.
    function subscribe(reg, key) {
        return reg.pushManager.getSubscription().then(function (old) {
            if (old && sameKey(old, key)) return old;
            var drop = old ? post('push-unsubscribe', { endpoint: old.endpoint }).catch(function () {}).then(function () { return old.unsubscribe(); }) : Promise.resolve();
            return drop.then(function () {
                return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(key) });
            });
        });
    }

    function register() {
        return navigator.serviceWorker.register(base + '/sw.js', { scope: scope });
    }

    window.NfeliaPush = {
        supported: function () {
            return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
        },
        current: function () {
            if (!this.supported()) return Promise.resolve(null);
            return navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                return reg ? reg.pushManager.getSubscription() : null;
            });
        },
        enable: function (publicKey) {
            return Notification.requestPermission().then(function (perm) {
                if (perm !== 'granted') throw new Error('Notifications were not allowed. You can allow them in your browser settings.');
                return register();
            }).then(function () {
                return navigator.serviceWorker.ready;
            }).then(function (reg) {
                return subscribe(reg, publicKey);
            }).then(function (sub) {
                return post('push-subscribe', sub.toJSON()).then(function () { return sub; });
            });
        },
        disable: function () {
            return this.current().then(function (sub) {
                if (!sub) return;
                return post('push-unsubscribe', { endpoint: sub.endpoint }).finally(function () { return sub.unsubscribe(); });
            });
        },
        sync: function (publicKey) {
            if (!this.supported()) return Promise.resolve(false);
            return navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                return reg ? reg.pushManager.getSubscription().then(function (sub) {
                    if (!sub) return false;
                    var fresh = (!publicKey || sameKey(sub, publicKey) || Notification.permission !== 'granted')
                        ? Promise.resolve(sub) : subscribe(reg, publicKey);
                    return fresh.then(function (s) { return post('push-subscribe', s.toJSON()); }).then(function () { return true; });
                }) : false;
            });
        }
    };
})();
