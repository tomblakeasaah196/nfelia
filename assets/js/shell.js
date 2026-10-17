/**
 * Nfelia app shell: what makes the signed-in pages behave like an app.
 *   - the app bar turns solid once the page scrolls
 *   - sheets (Create, Install, Country) open, close and give focus back
 *   - toasts: server flash messages and nfToast() from scripts
 *   - a banner when the network is gone or unstable
 *   - Back goes back, and pages slide the right way
 *   - the invitation to install, after sign-in on a phone
 *   - text size and background, saved on this device
 *   - the service worker, for speed and offline reading
 * No dependencies. Loaded with `defer` by views/layouts/app.php.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    };
    // A copy of the page kept on the phone (sw.js), shown with no network.
    var kept = document.querySelector('meta[name="nf-cached"]');
    if (kept) {
        // Text size and background may have changed since it was saved.
        var ui = (document.cookie.match(/(?:^|;\s*)nf_ui=([^;]*)/) || [])[1];
        new URLSearchParams(ui ? decodeURIComponent(ui).replace(/;/g, '&') : '').forEach(function (v, k) {
            if ((k === 'text' && /^(small|standard|large)$/.test(v)) || (k === 'bg' && /^(white|cream)$/.test(v))) root.setAttribute('data-' + k, v);
        });
    }

    // ---------- App bar ----------
    var bar = document.querySelector('[data-appbar]');
    if (bar) {
        var onScroll = function () { bar.classList.toggle('is-scrolled', window.scrollY > 4); };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // ---------- Sheets ----------
    function closeSheet(dlg) {
        if (!dlg.open || dlg.classList.contains('is-closing')) return;
        if (reduceMotion) { dlg.close(); return; }
        dlg.classList.add('is-closing');
        var done = function () { dlg.classList.remove('is-closing'); dlg.close(); };
        dlg.addEventListener('animationend', done, { once: true });
        setTimeout(function () { if (dlg.open) done(); }, 300);
    }
    function openSheet(id) {
        var dlg = document.getElementById(id);
        if (dlg && !dlg.open && typeof dlg.showModal === 'function') {
            dlg.showModal();
            // Focus the title, so a screen reader reads it and no button looks chosen.
            var title = document.getElementById(dlg.getAttribute('aria-labelledby') || '');
            if (title) { title.setAttribute('tabindex', '-1'); title.setAttribute('data-sheet-title', ''); title.focus({ preventScroll: true }); }
        }
        return dlg;
    }
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-open]');
        if (opener) { e.preventDefault(); openSheet(opener.getAttribute('data-open')); return; }
        var closer = e.target.closest('dialog.nf-sheet [data-close]');
        if (closer) { closeSheet(closer.closest('dialog')); return; }
        // A tap on the dimmed area outside a sheet closes it.
        if (e.target.matches && e.target.matches('dialog.nf-sheet')) {
            var r = e.target.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) closeSheet(e.target);
        }
    });
    document.querySelectorAll('dialog.nf-sheet').forEach(function (dlg) {
        dlg.addEventListener('cancel', function (e) { e.preventDefault(); closeSheet(dlg); });
        // Following a link inside a sheet: close it so Back doesn't reopen it.
        dlg.addEventListener('click', function (e) { if (e.target.closest('a[href]')) setTimeout(function () { if (dlg.open) dlg.close(); }, 400); });
    });

    // ---------- Toasts ----------
    var toasts = document.getElementById('nf-toasts');
    function dismiss(t) {
        if (!t || t.classList.contains('is-leaving')) return;
        if (reduceMotion) { t.remove(); return; }
        t.classList.add('is-leaving');
        t.addEventListener('animationend', function () { t.remove(); }, { once: true });
        setTimeout(function () { t.remove(); }, 300);
    }
    function arm(t, ms) {
        var timer = setTimeout(function () { dismiss(t); }, ms);
        t.addEventListener('pointerenter', function () { clearTimeout(timer); });
        t.addEventListener('focusin', function () { clearTimeout(timer); });
        t.addEventListener('pointerleave', function () { timer = setTimeout(function () { dismiss(t); }, 1500); });
        var x = t.querySelector('.x');
        if (x) x.addEventListener('click', function () { dismiss(t); });
    }
    var ICONS = {
        success: '<circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.8 2.8L16 9.5"/>',
        error: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/>',
        warning: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/>'
    };
    function svg(inner) {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + inner + '</svg>';
    }
    var X = svg('<path d="M18 6 6 18M6 6l12 12"/>');
    window.nfToast = function (message, type) {
        if (!toasts) return;
        type = ICONS[type] ? type : 'success';
        var t = document.createElement('div');
        t.className = 'nf-toast is-' + type;
        t.innerHTML = svg(ICONS[type]) + '<span></span><button type="button" class="x" aria-label="Dismiss">' + X + '</button>';
        t.querySelector('span').textContent = message;
        toasts.appendChild(t);
        arm(t, 4000);
    };
    if (toasts) toasts.querySelectorAll('[data-toast]').forEach(function (t) { if (kept) t.remove(); else arm(t, 4500); });

    // ---------- Network banner ----------
    // Offline, or slow enough to feel broken: say so, then get out of the way
    // (it closes itself after 5 seconds, or with the ✕).
    var net = null;
    function showNet(kind, text, detail, action) {
        if (net) net.remove();
        net = document.createElement('div');
        net.className = 'nf-net' + (kind === 'unstable' ? ' is-unstable' : '');
        net.setAttribute('role', 'status');
        var icon = kind === 'online'
            ? '<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01"/>'
            : kind === 'unstable'
                ? '<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01"/><path d="M2 8.8a15 15 0 0 1 8-3.6" opacity=".35"/>'
                : '<path d="m3 3 18 18M8.5 16a5 5 0 0 1 7 0M12 19.5h.01M5 12.5a10 10 0 0 1 4.2-2.4M16.4 10.6a10 10 0 0 1 2.6 1.9M2 8.8a15 15 0 0 1 5.1-2.9M12.8 5.2A15 15 0 0 1 22 8.8"/>';
        if (kind === 'fresh') icon = '<path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"/>';
        net.innerHTML = svg(icon) + '<span></span><button type="button" class="x" aria-label="Dismiss">' + X + '</button>';
        net.querySelector('span').textContent = text;
        if (detail) { var s = document.createElement('small'); s.textContent = detail; net.querySelector('span').appendChild(s); }
        if (action) {
            var go = document.createElement('button');
            go.type = 'button';
            go.className = 'go';
            go.textContent = action.label;
            go.addEventListener('click', action.run);
            net.insertBefore(go, net.querySelector('.x'));
        }
        document.body.appendChild(net);
        var me = net;
        var hide = function () {
            if (!me.isConnected) return;
            if (reduceMotion) { me.remove(); return; }
            me.classList.add('is-leaving');
            setTimeout(function () { me.remove(); }, 200);
        };
        me.querySelector('.x').addEventListener('click', hide);
        if (!action) setTimeout(hide, kind === 'online' ? 2500 : 5000);
    }
    function clock(iso) {
        var d = new Date(iso);
        if (isNaN(d)) return '';
        return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }
    if (kept) {
        showNet(navigator.onLine ? 'unstable' : 'offline', navigator.onLine ? 'Unstable network' : 'You’re offline', 'Showing this page as it was at ' + clock(kept.content));
    } else if (!navigator.onLine) {
        showNet('offline', 'You’re offline');
    } else {
        var nav = performance.getEntriesByType && performance.getEntriesByType('navigation')[0];
        var conn = navigator.connection || {};
        if ((nav && nav.responseEnd - nav.requestStart > 6000) || conn.effectiveType === 'slow-2g' || conn.effectiveType === '2g') {
            showNet('unstable', 'Unstable network', 'Pages may take longer');
        }
    }
    window.addEventListener('offline', function () { showNet('offline', 'You’re offline', 'You can still read what you’ve opened'); });
    window.addEventListener('online', function () { showNet('online', 'Back online'); });
    // Sending a form with no network loses what was typed. Keep it on screen instead.
    document.addEventListener('submit', function (e) {
        if (navigator.onLine !== false || e.defaultPrevented) return;
        e.preventDefault();
        showNet('offline', 'You’re offline', 'Nothing was sent. Your changes are still here.');
    }, true);

    // ---------- Text size and background (this device) ----------
    document.addEventListener('change', function (e) {
        var input = e.target;
        var key = input.getAttribute && input.getAttribute('data-ui');
        if (key !== 'text' && key !== 'bg') return;
        root.setAttribute('data-' + key, input.value);
        document.querySelectorAll('[data-ui="' + key + '"]').forEach(function (i) { i.checked = i.value === input.value; });
        var value = 'text=' + (root.getAttribute('data-text') || 'standard') + '&bg=' + (root.getAttribute('data-bg') || 'white');
        document.cookie = 'nf_ui=' + encodeURIComponent(value) + '; path=/; max-age=31536000; samesite=lax' + (location.protocol === 'https:' ? '; secure' : '');
        var meta = document.querySelector('meta[name="theme-color"]');
        var colour = root.getAttribute('data-bg') === 'cream' ? '#F7F3EA' : '#FFFFFF';
        if (meta && !root.classList.contains('nf-splashing')) meta.setAttribute('content', colour);
        if (meta) meta.setAttribute('data-theme-color', colour);
    });

    // ---------- Back, and which way pages move ----------
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-back]');
        if (!b || e.metaKey || e.ctrlKey) return;
        var ref = document.referrer;
        if (ref && ref.indexOf(location.origin) === 0 && history.length > 1) { e.preventDefault(); history.back(); }
    });
    var TAB_ROOTS = ['/dashboard', '/invoices', '/tasks', '/more', '/'];
    window.addEventListener('pagereveal', function (e) {
        if (!e.viewTransition || !window.navigation || !navigation.activation) return;
        var a = navigation.activation;
        var from = a.from && a.from.url ? new URL(a.from.url) : null;
        var to = a.entry && a.entry.url ? new URL(a.entry.url) : null;
        if (!from || !to) return;
        var type = 'forward';
        if (a.navigationType === 'traverse' && a.entry.index < a.from.index) type = 'back';
        else if (TAB_ROOTS.indexOf(from.pathname) > -1 && TAB_ROOTS.indexOf(to.pathname) > -1) type = 'tab';
        else if (to.pathname.split('/').length < from.pathname.split('/').length) type = 'back';
        e.viewTransition.types.add(type);
    });

    // ---------- Install ----------
    var install = document.getElementById('nf-install');
    var deferred = null;
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var isPhone = window.matchMedia && window.matchMedia('(pointer: coarse)').matches && Math.min(screen.width, screen.height) < 820;
    function canInstall() { return !standalone && (deferred || isIOS); }
    function syncInstallRows() {
        document.querySelectorAll('[data-install-row]').forEach(function (r) { r.hidden = !canInstall(); });
    }
    function showInstall() {
        if (!install || !canInstall()) return;
        install.querySelector('[data-install-go]').hidden = !deferred;
        install.querySelector('[data-install-ios]').hidden = !!deferred || !isIOS;
        install.querySelector('[data-install-done]').hidden = !!deferred || !isIOS;
        openSheet('nf-install');
    }
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();                                  // our own sheet instead of the browser's bar
        deferred = e;
        syncInstallRows();
    });
    window.addEventListener('appinstalled', function () {
        deferred = null;
        syncInstallRows();
        if (install && install.open) closeSheet(install);
        window.nfToast('Nfelia is on your home screen', 'success');
    });
    if (install) {
        install.querySelector('[data-install-go]').addEventListener('click', function () {
            if (!deferred) return;
            deferred.prompt();
            deferred.userChoice.finally(function () { deferred = null; syncInstallRows(); closeSheet(install); });
        });
        install.querySelector('[data-install-done]').addEventListener('click', function () { closeSheet(install); });
        install.querySelector('[data-install-later]').addEventListener('click', function () {
            store.set('nf.install.snooze', String(Date.now() + 7 * 864e5));
            closeSheet(install);
        });
    }
    document.addEventListener('click', function (e) { if (e.target.closest('[data-install-row]')) showInstall(); });
    syncInstallRows();
    // Right after signing in on a phone (lock.js calls this once the passkey
    // offer is done), unless "Not now" was pressed in the last 7 days.
    // Android tells us it can install a moment after load.
    window.nfInstallOffer = function () {
        if (kept || !isPhone || standalone || Number(store.get('nf.install.snooze') || 0) >= Date.now()) return;
        setTimeout(showInstall, deferred || isIOS ? 200 : 2500);
    };

    // ---------- Service worker ----------
    if ('serviceWorker' in navigator && window.isSecureContext) {
        var base = window.Nfelia ? window.Nfelia.url : '';
        navigator.serviceWorker.addEventListener('message', function (e) {
            var d = e.data || {};
            if (d.type === 'nf-fresh' && kept) {
                showNet('fresh', 'A newer version of this page is ready', '', { label: 'Refresh', run: function () { location.reload(); } });
            }
        });
        if (navigator.serviceWorker.startMessages) navigator.serviceWorker.startMessages();
        var controlled = !!navigator.serviceWorker.controller;
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }).catch(function () {});
            // This page loaded before the worker was in charge: hand it the
            // files the page used, so the next page and offline both have them.
            if (!controlled) navigator.serviceWorker.ready.then(function (reg) {
                var urls = [].map.call(document.querySelectorAll('link[rel="stylesheet"][href], script[src], link[rel="preload"][href]'), function (el) { return el.href || el.src; });
                if (reg.active) reg.active.postMessage({ type: 'nf-warm', urls: urls });
            });
        });
    }
})();
