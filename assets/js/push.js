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
 *   NfeliaPush.sync()           re-save an existing subscription (cheap)
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
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) {
                if (!r.ok || d.error) throw new Error(d.error || 'Could not reach Nfelia. Check your connection.');
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
                return reg.pushManager.getSubscription().then(function (old) {
                    return old || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(publicKey) });
                });
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
        sync: function () {
            return this.current().then(function (sub) {
                return sub ? post('push-subscribe', sub.toJSON()).then(function () { return true; }) : false;
            });
        }
    };
})();
