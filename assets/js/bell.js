/**
 * The bell in the app bar (views/layouts/app.php), on every app page.
 *
 * - The unread number stays current: on load, whenever the page comes back
 *   into view, every half minute while it is in view, and the moment a phone
 *   notification arrives (sw.js tells the page). The app icon shows the same
 *   number where the phone supports it.
 * - A notification that arrives while Nfelia is open also shows as a banner
 *   under the app bar (nfAlert): the alert's title and words, a tap opens
 *   its page, ✕ dismisses it, and it goes by itself after 3 seconds unless a
 *   finger is on it. Only alerts newer than the last one this phone saw, and
 *   only recent ones, so opening Nfelia after a day away shows the number on
 *   the bell, not a parade of banners. It works even where the phone can't
 *   buzz (no permission, no subscription, quiet hours): what the bell knows,
 *   the banner shows.
 * - Each row opens notifications/open/{id}, which marks it read and goes to
 *   the exact page.
 * - This phone's subscription is re-saved now and then (assets/js/push.js),
 *   so a renewed or reassigned one keeps working.
 * - A page with unsaved typing says so when sw.js asks, so tapping a phone
 *   notification opens a new window instead of leaving the form (P18).
 */
(function () {
    'use strict';
    var N = window.Nfelia || {};
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    };

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

    // ---------- The banner ----------
    var SHOW_MS = 3000;        // how long a banner stays, asked for by the owner
    var FRESH_S = 120;         // only an alert this recent gets a banner; older ones just count on the bell
    var MAX_AT_ONCE = 3;       // a burst (several orders at once) shows the newest few, one after another
    function svg(inner) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
    }
    var BELL = '<path d="M6 16v-5a6 6 0 1 1 12 0v5l1.5 2h-15Z"/><path d="M10 21h4"/>';
    var CLOSE = '<path d="M18 6 6 18M6 6l12 12"/>';
    var queue = [];
    var showing = null;

    function leave(el, then) {
        if (!el.isConnected) { then(); return; }
        if (reduceMotion) { el.remove(); then(); return; }
        el.classList.add('is-leaving');
        var done = false;
        var finish = function () { if (done) return; done = true; el.remove(); then(); };
        el.addEventListener('animationend', finish, { once: true });
        setTimeout(finish, 300);
    }

    function nextBanner() {
        if (showing) return;
        if (!queue.length) return;
        // Nothing shows while the page is hidden: it would be gone before it was seen.
        if (document.hidden) return;
        var n = queue.shift();
        // Queued while the page was hidden and long since: the phone buzzed
        // for it then, and the bell has it; a banner now would be noise.
        if (n.queued && Date.now() - n.queued > FRESH_S * 1000) { nextBanner(); return; }
        var el = document.createElement('div');
        el.className = 'nf-alert';
        el.setAttribute('role', 'status');
        el.setAttribute('data-alert', String(n.id));
        el.innerHTML = '<a class="body"><span class="ic">' + svg(BELL) + '</span><span class="txt"><b></b><small></small></span></a>'
                     + '<button type="button" class="x" aria-label="Dismiss">' + svg(CLOSE) + '</button>';
        var link = el.querySelector('a');
        link.href = N.url + '/' + n.url;
        link.querySelector('b').textContent = n.title || 'Nfelia';
        link.querySelector('small').textContent = n.message || '';
        document.body.appendChild(el);
        showing = el;

        var timer = null;
        var gone = false;
        var go = function () {
            if (gone) return;
            gone = true;
            clearTimeout(timer);
            leave(el, function () { showing = null; nextBanner(); });
        };
        var hold = function () { clearTimeout(timer); timer = null; };
        var release = function () { if (!gone && timer === null) timer = setTimeout(go, 1500); };
        el.addEventListener('pointerenter', hold);
        el.addEventListener('pointerleave', release);
        el.addEventListener('touchstart', hold, { passive: true });
        el.addEventListener('touchend', release);
        el.addEventListener('touchcancel', release);
        el.addEventListener('focusin', hold);
        el.addEventListener('focusout', release);
        el.querySelector('.x').addEventListener('click', go);
        link.addEventListener('click', function () { clearTimeout(timer); });   // the page is about to change
        timer = setTimeout(go, SHOW_MS);
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) nextBanner(); });

    /** Show a banner for an alert (title, message, url, id). Pages may call this too. */
    window.nfAlert = function (n) {
        if (!n || !n.url) return;
        if (!n.queued) n.queued = Date.now();
        queue.push(n);
        nextBanner();
    };

    // ---------- What this phone has already seen ----------
    // The newest alert id this person's phone has seen, kept on the device,
    // so a page change (each page is its own document) doesn't lose an alert
    // that arrived in between, and nothing old is ever bannered twice.
    var SEEN = 'nf.seen.' + ((N.lock && N.lock.user) || 0);
    var seen = +store.get(SEEN) || 0;
    function markSeen(latest) {
        if (latest > seen) { seen = latest; store.set(SEEN, String(latest)); }
    }
    /** Fresh, unread, unseen rows from a list, oldest first, at most a few. */
    function fresh(list) {
        var out = list.filter(function (n) {
            return n.id > seen && !+n.is_read && (typeof n.age !== 'number' || n.age <= FRESH_S);
        });
        out.sort(function (a, b) { return a.id - b.id; });
        return out.slice(-MAX_AT_ONCE);
    }

    // Alpine component: x-data="nfBell()".
    window.nfBell = function () {
        return {
            count: 0,
            notifications: [],
            loaded: false,
            loading: false,
            failed: false,
            latest: 0,

            init: function () {
                var self = this;
                this.refresh();
                document.addEventListener('visibilitychange', function () { if (!document.hidden) self.refresh(); });
                window.addEventListener('focus', function () { self.refresh(); });
                window.addEventListener('pageshow', function (e) { if (e.persisted) self.refresh(); });
                setInterval(function () { if (!document.hidden) self.refresh(); }, 30000);
                if (navigator.serviceWorker) {
                    navigator.serviceWorker.addEventListener('message', function (e) {
                        if (e.data && e.data.type === 'nf-notify') self.refresh(true);
                    });
                }
            },

            // The number first (cheap). The list only when the dropdown has
            // been opened, or when something arrived that this phone hasn't
            // seen yet (then it may deserve a banner).
            refresh: function (withList) {
                var self = this;
                if (withList || this.loaded) return this.load();
                return api('count').then(function (d) {
                    self.count = d.count || 0;
                    badge(self.count);
                    if (d.csrf && typeof d.csrf === 'string') N.csrf = d.csrf;   // a sign-in elsewhere may have changed it
                    if (Array.isArray(d.devices)) window.dispatchEvent(new CustomEvent('nf:devices', { detail: { devices: d.devices, dead: d.dead || [] } }));
                    var latest = +d.latest || 0;
                    if (!seen) { markSeen(latest); return; }     // first look on this device: nothing to announce
                    if (latest > seen) return self.load();
                }).catch(function () {});
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
                    if (Array.isArray(d.devices)) window.dispatchEvent(new CustomEvent('nf:devices', { detail: { devices: d.devices, dead: d.dead || [] } }));
                    var latest = +d.latest || 0;
                    if (seen) fresh(self.notifications).forEach(window.nfAlert);
                    markSeen(latest);
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
    // Kept working without anyone doing anything (assets/js/push.js heal):
    // the bell's answers list the phones Nfelia has for this person and the
    // ones the push service declared dead; if this phone isn't listed (lost
    // after a dead push, replaced by the browser) or is dead, or the browser
    // dropped the subscription while permission is still granted, it is
    // made and saved again. At most once every few minutes, and again when
    // the page comes back into view.
    var healedAt = 0;
    var TOLD = 'nf.push.told.' + ((N.lock && N.lock.user) || 0);
    function heal(known) {
        var push = window.NfeliaPush;
        if (!push || !N.push || !N.push.key || !push.supported()) return;
        if (Date.now() - healedAt < 5 * 60 * 1000) return;
        healedAt = Date.now();
        push.heal(N.push.key, known).then(function () { return push.state(N.push.key, known); }).then(function (state) {
            // The browser switched alerts off (Chrome resets permissions for
            // sites it thinks unused): say so once, here, where it can be fixed.
            if (state !== 'reset' || store.get(TOLD) === '1') return;
            store.set(TOLD, '1');
            window.nfAlert({ id: 0, title: 'Phone alerts stopped on this phone', message: 'Your browser switched them off. Tap to turn them back on.', url: 'more#notifications' });
        }).catch(function () {});
    }
    window.addEventListener('nf:devices', function (e) { heal(e.detail); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) healedAt = 0; });

    // ---------- After signing in on a phone: the offer to turn alerts on ----------
    // Once, after the passkey and install offers (lock.js, shell.js), so
    // nobody is left without phone alerts just because they never found the
    // switch. "Not now" waits 7 days.
    window.nfPushOffer = function () {
        var push = window.NfeliaPush;
        var sheet = document.getElementById('nf-push-offer');
        if (!sheet || !push || !N.push || !N.push.key || !push.supported()) return;
        if (!window.matchMedia || !window.matchMedia('(pointer: coarse)').matches) return;
        if (Notification.permission !== 'default' || push.wanted()) return;
        if (Number(store.get('nf.push.snooze') || 0) > Date.now()) return;
        if (typeof sheet.showModal !== 'function' || sheet.open) return;
        var go = sheet.querySelector('[data-push-go]');
        var note = sheet.querySelector('[data-push-err]');
        sheet.querySelector('[data-push-later]').addEventListener('click', function () {
            store.set('nf.push.snooze', String(Date.now() + 7 * 864e5));
            sheet.close();
        });
        go.addEventListener('click', function () {
            go.disabled = true;
            note.hidden = true;
            push.enable(N.push.key).then(function () {
                sheet.close();
                if (window.nfToast) window.nfToast('Alerts are on for this phone.', 'success');
            }, function (err) {
                note.textContent = (err && err.message) || 'Not turned on. Try again from More › Notifications.';
                note.hidden = false;
                go.disabled = false;
            });
        });
        sheet.showModal();
        var heading = document.getElementById('nf-push-offer-h');
        if (heading) { heading.setAttribute('tabindex', '-1'); heading.setAttribute('data-sheet-title', ''); heading.focus({ preventScroll: true }); }
    };
})();
