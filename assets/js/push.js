/**
 * Nfelia — phone notifications (web push), shared by Tasks & Calendar,
 * More > Notifications and the bell. Registers /sw.js, asks permission only
 * when the person taps "Turn on", and keeps the server's copy of the
 * subscription in step with the browser's.
 *
 *   NfeliaPush.supported()      can this browser do it at all?
 *   NfeliaPush.current()        the live subscription, or null
 *   NfeliaPush.enable(key)      ask, subscribe, save on the server
 *   NfeliaPush.disable()        this person's alerts off here (the browser's
 *                               subscription goes only when nobody else on
 *                               this phone still uses it)
 *   NfeliaPush.sync(key)        re-save an existing subscription (cheap),
 *                               or renew it if Nfelia's key has changed
 *   NfeliaPush.heal(key, known) this phone had alerts on: make sure it still
 *                               has a subscription and the server knows it
 *                               (known: {devices, dead} from the bell's count
 *                               call, or null)
 *   NfeliaPush.state(key, known) what to tell the person: 'on', 'off',
 *                               'reset' (the browser switched them off),
 *                               'blocked', 'unsupported', 'ios', 'unavailable'
 *   NfeliaPush.hash(endpoint)   the first part of the endpoint's SHA-256, as
 *                               the server lists it
 *
 * The phone remembers it had alerts turned on for this person
 * (nf.push.on.<user>), so when a browser drops the subscription (they
 * expire; Chrome tidies unused sites) the next visit quietly makes a new one
 * instead of staying silent for good. The service worker is handed a device
 * token too (sw.js), so it can save a subscription the browser replaced
 * while Nfelia was closed. One phone can hold alerts for several people.
 */
(function () {
    var base = (window.Nfelia && window.Nfelia.url) || location.origin;
    var scope = new URL(base + '/').pathname;
    var user = (window.Nfelia && window.Nfelia.lock && window.Nfelia.lock.user) || 0;
    var ON = 'nf.push.on.' + user;         // this person turned alerts on, on this phone
    var ARMED = 'nf.push.armed.' + user;   // the worker holds this person's device token
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
        drop: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
    };

    function post(action, body) {
        return fetch(base + '/api/notifications?action=' + action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf, 'X-Requested-With': 'XMLHttpRequest', 'X-Nfelia-Background': '1' },
            body: JSON.stringify(body)
        }).catch(function () {
            throw new Error('No connection. Check your data and try again.');
        }).then(function (r) {
            if (r.redirected) throw new Error('You were signed out. Reload the page to sign in again.');
            return r.json().catch(function () { return null; }).then(function (d) {
                if (!r.ok || !d || d.error) throw new Error((d && (d.error || d.message)) || 'Could not reach Nfelia. Try again.');
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

    function hash(endpoint) {
        if (!window.crypto || !crypto.subtle || !window.TextEncoder) return Promise.resolve(null);
        return crypto.subtle.digest('SHA-256', new TextEncoder().encode(endpoint)).then(function (buf) {
            var bytes = new Uint8Array(buf), hex = '';
            for (var i = 0; i < 8; i++) hex += ('0' + bytes[i].toString(16)).slice(-2);
            return hex;
        }).catch(function () { return null; });
    }

    // A subscription made with Nfelia's current key: the one this phone has,
    // or a new one when there is none, the key has changed since, or the
    // push service has declared the old one dead ($dead: the server's list).
    function subscribe(reg, key, dead) {
        return reg.pushManager.getSubscription().then(function (old) {
            if (!old) return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(key) });
            var isDead = Array.isArray(dead) && dead.length ? hash(old.endpoint).then(function (h) { return h !== null && dead.indexOf(h) !== -1; }) : Promise.resolve(false);
            return isDead.then(function (gone) {
                if (sameKey(old, key) && !gone) return old;
                var drop = gone ? Promise.resolve() : post('unsubscribe', { endpoint: old.endpoint }).catch(function () {});
                return drop.then(function () { return old.unsubscribe(); }).catch(function () {}).then(function () {
                    return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(key) });
                });
            });
        });
    }

    function register() {
        return navigator.serviceWorker.register(base + '/sw.js', { scope: scope });
    }

    // Save on the server, then hand the worker what it needs to save a
    // replacement by itself (sw.js, pushsubscriptionchange).
    function save(sub, key) {
        return post('subscribe', sub.toJSON()).then(function (d) {
            store.set(ON, '1');
            if (d && d.token) {
                navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                    var w = reg && (reg.active || reg.waiting || reg.installing);
                    if (w) { w.postMessage({ type: 'nf-push-config', user: user, token: d.token, key: key }); store.set(ARMED, '1'); }
                }).catch(function () {});
            }
            return d;
        });
    }

    function registration() {
        return navigator.serviceWorker.getRegistration(scope);
    }

    window.NfeliaPush = {
        supported: function () {
            return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext;
        },
        /** This phone was turned on for alerts by this person, as far as it remembers. */
        wanted: function () { return store.get(ON) === '1'; },
        hash: hash,
        current: function () {
            if (!this.supported()) return Promise.resolve(null);
            return registration().then(function (reg) {
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
                return subscribe(reg, publicKey, null);
            }).then(function (sub) {
                return save(sub, publicKey).then(function () { return sub; });
            });
        },
        disable: function () {
            store.drop(ON);
            store.drop(ARMED);
            return this.current().then(function (sub) {
                if (!sub) return;
                return post('unsubscribe', { endpoint: sub.endpoint }).then(function (d) {
                    // Someone else on this phone still has alerts on: the browser keeps its subscription for them.
                    if (d && d.others > 0) return;
                    return sub.unsubscribe();
                }, function (err) {
                    return sub.unsubscribe().then(function () { throw err; });
                });
            });
        },
        sync: function (publicKey) {
            if (!this.supported()) return Promise.resolve(false);
            return registration().then(function (reg) {
                return reg ? reg.pushManager.getSubscription().then(function (sub) {
                    if (!sub) return false;
                    var fresh = (!publicKey || sameKey(sub, publicKey) || Notification.permission !== 'granted')
                        ? Promise.resolve(sub) : subscribe(reg, publicKey, null);
                    return fresh.then(function (s) { return save(s, publicKey); }).then(function () { return true; });
                }) : false;
            });
        },
        /**
         * Keep this phone's alerts working without anyone doing anything.
         * Permission is granted and this person turned alerts on here (or the
         * server lists this phone for them), so: a missing subscription is
         * made again; one the push service declared dead is replaced; one the
         * server doesn't list (lost, replaced while Nfelia was closed) is
         * saved again; and the worker is handed the device token once.
         * Resolves to what was done: 'ok', 'saved', 'renewed' or 'nothing'.
         * $known is {devices: [...], dead: [...]} from the server when the
         * caller has it (the bell's count answer); null to skip the check.
         */
        heal: function (publicKey, known) {
            var self = this;
            if (!this.supported() || !publicKey || Notification.permission !== 'granted') return Promise.resolve('nothing');
            var devices = known && Array.isArray(known.devices) ? known.devices : null;
            var dead = known && Array.isArray(known.dead) ? known.dead : [];
            return registration().then(function (reg) {
                if (!reg) return self.wanted() ? register().then(function () { return navigator.serviceWorker.ready; }) : null;
                return reg;
            }).then(function (reg) {
                if (!reg) return 'nothing';
                return reg.pushManager.getSubscription().then(function (sub) {
                    if (!sub) {
                        if (!self.wanted()) return 'nothing';
                        return subscribe(reg, publicKey, dead).then(function (s) { return save(s, publicKey); }).then(function () { return 'renewed'; });
                    }
                    return hash(sub.endpoint).then(function (h) {
                        var listed = devices === null ? null : (h === null ? true : devices.indexOf(h) !== -1);
                        var gone = h !== null && dead.indexOf(h) !== -1;
                        if (gone || !sameKey(sub, publicKey)) {
                            // A dead or outdated subscription: a new one, for everyone who had it... which is this person, here.
                            if (!self.wanted() && !listed) return 'nothing';
                            return subscribe(reg, publicKey, dead).then(function (s) { return save(s, publicKey); }).then(function () { return 'renewed'; });
                        }
                        if (listed === true) {
                            // Alerts were on here before this page knew to remember it: remember now, and arm the worker once.
                            store.set(ON, '1');
                            if (!store.get(ARMED)) return save(sub, publicKey).then(function () { return 'saved'; });
                            return 'ok';
                        }
                        if (listed === false && self.wanted()) return save(sub, publicKey).then(function () { return 'saved'; });
                        if (listed === null && self.wanted() && !store.get(ARMED)) return save(sub, publicKey).then(function () { return 'saved'; });
                        return listed === null ? 'ok' : 'nothing';
                    });
                });
            }).catch(function () { return 'nothing'; });
        },
        /** What this phone should be told about alerts, after heal() has had its go. */
        state: function (publicKey, known) {
            var iOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
            if (!publicKey) return Promise.resolve('unavailable');
            if (!this.supported()) return Promise.resolve(iOS && !standalone ? 'ios' : 'unsupported');
            if (Notification.permission === 'denied') return Promise.resolve('blocked');
            var self = this;
            return this.current().then(function (sub) {
                if (!sub) return self.wanted() && Notification.permission === 'default' ? 'reset' : 'off';
                var devices = known && Array.isArray(known.devices) ? known.devices : null;
                if (devices === null) return 'on';
                return hash(sub.endpoint).then(function (h) { return h === null || devices.indexOf(h) !== -1 ? 'on' : 'off'; });
            }).catch(function () { return 'off'; });
        }
    };
})();
