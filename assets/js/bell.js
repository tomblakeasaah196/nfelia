/**
 * The bell in the app bar (views/layouts/app.php), on every app page.
 *
 * - The unread number stays current: on load, whenever the page comes back
 *   into view, every minute while it is in view, and the moment a phone
 *   notification arrives (sw.js tells the page). The app icon shows the same
 *   number where the phone supports it.
 * - Each row opens notifications/open/{id}, which marks it read and goes to
 *   the exact page.
 * - This phone's subscription is re-saved now and then (assets/js/push.js),
 *   so a renewed or reassigned one keeps working.
 * - Signing out stops this phone getting that person's alerts.
 * - A page with unsaved typing says so when sw.js asks, so tapping a phone
 *   notification opens a new window instead of leaving the form (P18).
 */
(function () {
    'use strict';
    var N = window.Nfelia || {};

    function api(action, body) {
        var opts = { credentials: 'same-origin', headers: { 'X-Nfelia-Background': '1', 'X-Requested-With': 'XMLHttpRequest' } };
        if (body) {
            opts.method = 'POST';
            opts.headers['Content-Type'] = 'application/json';
            opts.headers['X-CSRF-Token'] = N.csrf;
            opts.body = JSON.stringify(body);
        }
        return fetch(N.url + '/api/notifications?action=' + action, opts).then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        });
    }

    function badge(count) {
        try {
            if (count > 0 && navigator.setAppBadge) navigator.setAppBadge(count).catch(function () {});
            else if (navigator.clearAppBadge) navigator.clearAppBadge().catch(function () {});
        } catch (e) {}
    }

    // Unsaved typing on this page: anything typed in a form, a sheet or a
    // text box since the page opened or last submitted. Search boxes don't
    // count.
    var dirty = false;
    document.addEventListener('input', function (e) {
        var t = e.target;
        if (!t || t.type === 'search' || (t.closest && t.closest('[role=search]'))) return;
        if (t.tagName === 'TEXTAREA' || (t.closest && t.closest('form, [x-ref=sheet], [role=dialog]'))) dirty = true;
    }, true);
    document.addEventListener('submit', function () { dirty = false; }, true);
    if (navigator.serviceWorker) {
        navigator.serviceWorker.addEventListener('message', function (e) {
            if (e.data && e.data.type === 'nf-dirty?' && e.ports && e.ports[0]) e.ports[0].postMessage({ dirty: dirty });
        });
    }

    // Alpine component: x-data="nfBell()".
    window.nfBell = function () {
        return {
            count: 0,
            notifications: [],
            loaded: false,
            loading: false,
            failed: false,

            init: function () {
                var self = this;
                this.refresh();
                document.addEventListener('visibilitychange', function () { if (!document.hidden) self.refresh(); });
                setInterval(function () { if (!document.hidden) self.refresh(); }, 60000);
                if (navigator.serviceWorker) {
                    navigator.serviceWorker.addEventListener('message', function (e) {
                        if (e.data && e.data.type === 'nf-notify') self.refresh(true);
                    });
                }
            },

            refresh: function (withList) {
                var self = this;
                if (withList || this.loaded) return this.load();
                return api('count').then(function (d) { self.count = d.count || 0; badge(self.count); }).catch(function () {});
            },

            load: function () {
                var self = this;
                this.loading = !this.loaded;
                this.failed = false;
                return api('list&limit=15').then(function (d) {
                    self.notifications = d.notifications || [];
                    self.count = d.count || 0;
                    self.loaded = true;
                    badge(self.count);
                }).catch(function () { self.failed = !self.loaded; })
                  .then(function () { self.loading = false; });
            },

            open: function (n) {
                if (!n.is_read) { n.is_read = 1; this.count = Math.max(0, this.count - 1); badge(this.count); }
                return N.url + '/' + n.url;
            },

            markAllRead: function () {
                var self = this;
                api('mark_read', { id: 'all' }).then(function () {
                    self.count = 0;
                    badge(0);
                    self.notifications.forEach(function (n) { n.is_read = 1; });
                }).catch(function () {});
            }
        };
    };

    // ---------- This phone's subscription ----------
    // Re-saved at most every 6 hours: cheap, and it heals a subscription the
    // browser renewed while Nfelia was closed, or one another person on this
    // phone had (it moves to whoever is signed in).
    document.addEventListener('DOMContentLoaded', function () {
        var push = window.NfeliaPush;
        if (!push || !N.push || !N.push.key || !push.supported() || Notification.permission !== 'granted') return;
        var last = 0;
        try { last = +localStorage.getItem('nf.pushSynced.' + N.lock.user) || 0; } catch (e) {}
        if (Date.now() - last < 6 * 3600 * 1000) return;
        push.sync(N.push.key).then(function (had) {
            if (had) try { localStorage.setItem('nf.pushSynced.' + N.lock.user, String(Date.now())); } catch (e) {}
        }).catch(function () {});
    });

    // ---------- Signing out ----------
    // The server forgets this phone for this person, and the browser drops
    // the subscription, so the next person to sign in here chooses for
    // themselves. Never holds sign-out up for more than a moment.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.action || !/\/logout$/.test(form.action) || form.dataset.pushChecked) return;
        if (!('serviceWorker' in navigator) || !window.PushManager) return;
        e.preventDefault();
        form.dataset.pushChecked = '1';
        var go = function () { form.submit(); };
        var timer = setTimeout(go, 1500);
        navigator.serviceWorker.getRegistration().then(function (reg) {
            return reg ? reg.pushManager.getSubscription() : null;
        }).then(function (sub) {
            if (!sub) return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'push_endpoint';
            input.value = sub.endpoint;
            form.appendChild(input);
            return sub.unsubscribe();
        }).catch(function () {}).then(function () { clearTimeout(timer); go(); });
    }, true);
})();
