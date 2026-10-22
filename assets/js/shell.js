/**
 * Nfelia app shell: what makes the signed-in pages behave like an app.
 *   - the app bar turns solid once the page scrolls
 *   - sheets (Create, Install, Country) open, close and give focus back
 *   - toasts: server flash messages and nfToast() from scripts
 *   - a banner when the network is gone or unstable
 *   - Back goes back, and pages slide the right way
 *   - the invitation to install, after sign-in on a phone
 *   - text size and background, saved on this device
 *   - typing on a phone: the field stays in sight above the keyboard
 *   - product photos: the original when a small copy can't be made
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
    // The business's colours, for the page shown with no connection
    // (offline.html): a file kept on the phone, it can't ask whose app it is in.
    var css = getComputedStyle(root);
    store.set('nf.colours', JSON.stringify(['accent', 'on-accent', 'accent-text', 'glow', 'glow-2'].reduce(function (saved, k) {
        saved[k] = css.getPropertyValue('--' + k).trim();
        return saved;
    }, {})));

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
    // For page scripts that open a sheet themselves (the order screen's item step).
    window.nfOpenSheet = openSheet;
    window.nfCloseSheet = function (id) { var dlg = document.getElementById(id); if (dlg) closeSheet(dlg); };
    // A link can open a sheet on the page it goes to: page#sheet:nf-cancel
    if (location.hash.indexOf('#sheet:') === 0) {
        var sheetId = location.hash.slice(7);
        var openFromLink = function () { if (openSheet(sheetId)) history.replaceState(null, '', location.pathname + location.search); };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', openFromLink); else openFromLink();
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

    // ---------- (i): the longer words behind a figure (nf_info() in app/helpers/ui.php) ----------
    document.addEventListener('click', function (e) {
        var info = e.target.closest('[data-info]');
        if (!info) return;
        e.preventDefault();
        var dlg = document.getElementById('nf-info');
        if (!dlg) return;
        dlg.querySelector('[data-info-title]').textContent = info.getAttribute('data-info-title') || '';
        dlg.querySelector('[data-info-text]').textContent = info.getAttribute('data-info') || '';
        openSheet('nf-info');
    });

    // ---------- Labels for icon-only buttons on a phone ----------
    // A hover shows [data-tip]; a finger can't hover, so pressing and holding
    // shows it instead, and that press doesn't also count as a tap.
    var tipTimer = null, tipShown = null;
    document.addEventListener('touchstart', function (e) {
        var el = e.target.closest('[data-tip]');
        if (!el) return;
        clearTimeout(tipTimer);
        tipTimer = setTimeout(function () {
            if (tipShown) tipShown.classList.remove('is-tip');
            tipShown = el;
            el.classList.add('is-tip');
            setTimeout(function () { el.classList.remove('is-tip'); if (tipShown === el) tipShown = null; }, 1600);
        }, 450);
    }, { passive: true });
    ['touchend', 'touchmove', 'touchcancel'].forEach(function (type) {
        document.addEventListener(type, function () { clearTimeout(tipTimer); }, { passive: true });
    });
    document.addEventListener('click', function (e) {
        if (tipShown && e.target.closest('[data-tip]') === tipShown) { e.preventDefault(); e.stopImmediatePropagation(); tipShown = null; }
    }, true);
    document.addEventListener('contextmenu', function (e) { if (e.target.closest('[data-tip]')) e.preventDefault(); });

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
        // Success says "done" and gets out of the way in 3s; a problem stays
        // longer so it can be read.
        arm(t, type === 'success' ? 3000 : 4500);
    };
    if (toasts) toasts.querySelectorAll('[data-toast]').forEach(function (t) { if (kept) t.remove(); else arm(t, t.classList.contains('is-success') ? 3000 : 4500); });

    // ---------- Network banner ----------
    // Offline, or slow enough to feel broken: say so, then get out of the way
    // (it closes itself after 5 seconds, or with the ✕).
    var net = null;
    function showNet(kind, text, detail, action) {
        if (net) net.remove();
        net = document.createElement('div');
        net.className = 'nf-net' + (kind === 'unstable' || kind === 'busy' ? ' is-unstable' : '');
        net.setAttribute('role', 'status');
        var icon = kind === 'online'
            ? '<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01"/>'
            : kind === 'unstable'
                ? '<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01"/><path d="M2 8.8a15 15 0 0 1 8-3.6" opacity=".35"/>'
                : '<path d="m3 3 18 18M8.5 16a5 5 0 0 1 7 0M12 19.5h.01M5 12.5a10 10 0 0 1 4.2-2.4M16.4 10.6a10 10 0 0 1 2.6 1.9M2 8.8a15 15 0 0 1 5.1-2.9M12.8 5.2A15 15 0 0 1 22 8.8"/>';
        if (kind === 'fresh') icon = '<path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"/>';
        if (kind === 'busy') icon = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
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
    if (kept && kept.getAttribute('data-why') === 'busy') {
        // The server answered, but too busy to send the page (sw.js)
        showNet('busy', 'Nfelia is busy', 'Showing this page as it was at ' + clock(kept.content), { label: 'Try again', run: function () { location.reload(); } });
    } else if (kept) {
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
    // A form that records money (data-once) goes once: a second tap while the
    // first is on its way does nothing. Coming back to the page frees it again.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || !form.hasAttribute || !form.hasAttribute('data-once')) return;
        if (form.getAttribute('data-sent') === '1') { e.preventDefault(); return; }
        form.setAttribute('data-sent', '1');
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-sent]').forEach(function (f) { f.removeAttribute('data-sent'); });
    });

    // ---------- Submit buttons: lock and spin while the page works ----------
    // Any form's submit button disables and turns into a spinner the moment the
    // form is sent, so no one taps twice and there is clear feedback while the
    // next page loads. Most forms then navigate, and the success toast lands on
    // the page they open. Opt out with data-no-spin on the form or the button —
    // forms that stay on the page (AJAX) carry their own spinner instead.
    function spinBtn(btn) {
        if (!btn || btn.dataset.nfSpin === '1') return;
        btn.dataset.nfSpin = '1';
        var rect = btn.getBoundingClientRect();
        if (rect.width) btn.style.minWidth = Math.ceil(rect.width) + 'px';
        btn.classList.add('is-spinning');
        btn.setAttribute('aria-busy', 'true');
        if (btn.tagName === 'BUTTON') {
            btn.dataset.nfLabel = btn.innerHTML;
            btn.innerHTML = '<span class="nf-spin"></span>';
        } else if (btn.tagName === 'INPUT') {
            btn.dataset.nfLabel = btn.value;
            btn.value = '…';
        }
        // Disabling is deferred so the submit this button is part of still goes.
        setTimeout(function () { try { btn.disabled = true; } catch (e) {} }, 0);
        // Safety net: if the page never navigates (a download, a quiet failure),
        // give the button back after a while.
        btn._nfSpinTimer = setTimeout(function () { unspinBtn(btn); }, 20000);
    }
    window.nfSpinButton = spinBtn;
    window.unspinBtn = function unspinBtn(btn) {
        if (!btn || btn.dataset.nfSpin !== '1') return;
        clearTimeout(btn._nfSpinTimer);
        btn.dataset.nfSpin = '';
        try { btn.disabled = false; } catch (e) {}
        btn.classList.remove('is-spinning');
        btn.removeAttribute('aria-busy');
        btn.style.minWidth = '';
        if ('nfLabel' in btn.dataset) {
            if (btn.tagName === 'BUTTON') btn.innerHTML = btn.dataset.nfLabel; else btn.value = btn.dataset.nfLabel;
            delete btn.dataset.nfLabel;
        }
    };
    var unspinBtn = window.unspinBtn;
    document.addEventListener('submit', function (e) {
        var form = e.target;
        // Skip when another handler stopped the submit (validation, AJAX): the
        // page is staying, so a stuck spinner would be wrong.
        if (e.defaultPrevented || !form.hasAttribute || form.hasAttribute('data-no-spin')) return;
        var btn = e.submitter || form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
        if (!btn || btn.hasAttribute('data-no-spin')) return;
        spinBtn(btn);
    });
    // Back/forward cache can restore the page with the button still spinning.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-nf-spin="1"], .is-spinning').forEach(unspinBtn);
    });

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

    // ---------- Photos ----------
    // A small copy that can't be made gives way to the original photo
    // (data-full); a photo that is gone leaves its placeholder showing.
    document.addEventListener('error', function (e) {
        var img = e.target;
        if (!img || img.tagName !== 'IMG' || !img.hasAttribute('data-full')) return;
        var full = img.getAttribute('data-full');
        if (full && img.getAttribute('src') !== full) { img.setAttribute('src', full); return; }
        img.hidden = true;
    }, true);

    // ---------- Typing: keep the field in sight ----------
    // A tap anywhere on a money box (the currency sign too) goes to its number.
    document.addEventListener('click', function (e) {
        var box = e.target.closest && e.target.closest('.nf-amount');
        if (!box || e.target.tagName === 'INPUT') return;
        var input = box.querySelector('input:not([type="hidden"])');
        if (input && document.activeElement !== input) input.focus();
    });
    // On a phone, when the keyboard comes up the tabs and the sticky Save bar
    // step aside (html.nf-typing in shell.css) and the field is brought to the
    // middle of what is left, clear of the app bar. They come back when the
    // keyboard goes, even if the field keeps focus (Android's Back key).
    var typeable = function (el) {
        if (!el || !el.tagName || el.disabled || el.readOnly) return false;
        if (el.isContentEditable || el.tagName === 'TEXTAREA') return true;
        return el.tagName === 'INPUT' && !/^(checkbox|radio|range|color|file|hidden|button|submit|reset|image)$/.test(el.type);
    };
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
        var vv = window.visualViewport;
        var viewH = function () { return vv ? vv.height : window.innerHeight; };
        var fullH = viewH();
        var keyboardUp = function () { return viewH() < fullH - 120; };
        var bring = function (el) {
            var r = el.getBoundingClientRect();
            var visTop = vv ? vv.offsetTop : 0;
            var top = Math.max(visTop, bar ? bar.getBoundingClientRect().bottom : 0) + 12;
            if (r.top >= top && r.bottom <= visTop + viewH() - 16) return;
            el.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
        };
        var syncTyping = function () {
            var el = document.activeElement;
            var up = typeable(el) && keyboardUp();
            root.classList.toggle('nf-typing', up);
            if (up) requestAnimationFrame(function () { bring(el); });
        };
        (vv || window).addEventListener('resize', function () {
            // The full height moves with rotation; learn it while nothing is typed in.
            if (!typeable(document.activeElement) || viewH() > fullH) fullH = viewH();
            syncTyping();
        });
        document.addEventListener('focusin', function (e) {
            if (!typeable(e.target)) return;
            // Field to field with the keyboard already up: no resize comes.
            if (keyboardUp()) syncTyping();
            // In case the keyboard's resize is missed or comes late.
            setTimeout(syncTyping, 400);
        });
        document.addEventListener('focusout', function () {
            setTimeout(function () { if (!typeable(document.activeElement)) root.classList.remove('nf-typing'); }, 60);
        });
    }

    // ---------- Back, and which way pages move ----------
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-back]');
        if (!b || e.metaKey || e.ctrlKey) return;
        var ref = document.referrer;
        if (ref && ref.indexOf(location.origin) === 0 && history.length > 1) { e.preventDefault(); history.back(); }
    });
    var TAB_ROOTS = ['/dashboard', '/invoices', '/receipts', '/quotations', '/tasks', '/more', '/'];
    // The browser drops a transition when the screen changes size before the
    // new page is painted (the address bar or keyboard moving), and rejects
    // its promises. That only means no animation this time: caught here, on
    // the page leaving and the page arriving, so it is never reported.
    function quietTransition(vt) {
        ['ready', 'finished', 'updateCallbackDone'].forEach(function (k) {
            if (vt[k] && vt[k].catch) vt[k].catch(function () {});
        });
    }
    window.addEventListener('pageswap', function (e) { if (e.viewTransition) quietTransition(e.viewTransition); });
    window.addEventListener('pagereveal', function (e) {
        if (e.viewTransition) quietTransition(e.viewTransition);
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
        if (kept) return;
        var pushOffer = function () { if (window.nfPushOffer) setTimeout(window.nfPushOffer, 300); };
        if (!isPhone || standalone || Number(store.get('nf.install.snooze') || 0) >= Date.now()) { if (isPhone) pushOffer(); return; }
        // The phone alerts offer (bell.js) comes after this one has closed.
        if (install) install.addEventListener('close', pushOffer, { once: true });
        setTimeout(function () { showInstall(); if (install && !install.open) { install.removeEventListener('close', pushOffer); pushOffer(); } }, deferred || isIOS ? 200 : 2500);
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
