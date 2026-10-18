/**
 * The lock, and passkeys inside the app.
 *
 *   - After Nfelia.lock.after seconds without use (2 hours), the page blurs
 *     and the lock asks for a passkey (fingerprint, face, phone PIN) or the
 *     password. Use in any tab counts; unlocking one tab unlocks them all.
 *     The server keeps the same clock (app/helpers/signin.php) and refuses
 *     to show anything until the lock is opened.
 *   - Right after signing in: the offer to sign in with a passkey next time,
 *     then the invitation to install (shell.js).
 *   - Settings: the list of passkeys, add and remove.
 * Needs assets/js/passkey.js. Loaded with `defer` by views/layouts/app.php.
 */
(function () {
    'use strict';

    var cfg = (window.Nfelia && window.Nfelia.lock) || null;
    var dlg = document.getElementById('nf-lock');
    if (!cfg || !dlg) return;

    var pk = window.nfPasskey || { supported: false };
    var root = document.documentElement;
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
        drop: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
    };
    var now = function () { return Date.now(); };
    // Signed out meanwhile: sign in again, then come back to this page.
    var signInHere = function () {
        var base = window.Nfelia.url, here = location.href.replace(/#.*$/, '');
        var path = here.indexOf(base + '/') === 0 ? here.slice(base.length + 1) : '';
        return base + '/login' + (path ? '?next=' + encodeURIComponent(path) : '');
    };
    var AFTER = cfg.after * 1000;
    var PK_KEY = 'nf.pk.' + cfg.user;          // this device has a passkey for this person
    var kept = !!document.querySelector('meta[name="nf-cached"]');

    // ---------- When was Nfelia last used, in any tab ----------
    // A page fresh from the server knows how long ago that was. A kept copy
    // (sw.js) knows nothing: only this device's own record counts.
    var fromServer = kept ? 0 : now() - (AFTER - cfg.left * 1000);
    var lastServer = kept ? 0 : now();
    var lastWrite = 0;
    function lastActive() { return Math.max(Number(store.get('nf.active')) || 0, fromServer); }
    if (!kept && !cfg.locked) store.set('nf.active', String(lastActive()));

    var locked = false;
    function used() {
        if (locked) return;
        var t = now();
        if (t - lastActive() > AFTER) { lock('return'); return; }
        if (t - lastWrite > 5000) { store.set('nf.active', String(t)); lastWrite = t; }
        // Reading or typing for a long time without opening a page: tell the
        // server, at most every 5 minutes, so it does not lock behind our back.
        if (t - Math.max(lastServer, Number(store.get('nf.served')) || 0) > 300000 && navigator.onLine !== false) {
            lastServer = t;
            pk.api && pk.api('ping').then(function (d) {
                if (d.ok) store.set('nf.served', String(now()));
                else if (d.locked) lock('server');
                else if (d.signedOut) location.href = signInHere();
            });
        }
    }
    ['pointerdown', 'keydown', 'wheel', 'touchstart'].forEach(function (type) {
        document.addEventListener(type, used, { passive: true, capture: true });
    });
    window.addEventListener('scroll', used, { passive: true });

    function check(reason) {
        if (!locked && now() - lastActive() > AFTER) lock(reason);
    }
    setInterval(function () { check('timer'); }, 15000);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') check('return'); });
    window.addEventListener('pageshow', function (e) { if (e.persisted) check('return'); });
    window.addEventListener('focus', function () { check('return'); });
    window.addEventListener('storage', function (e) {
        if (e.key === 'nf.unlocked' && locked) opened(true);
    });

    // The server said "locked" to a script on this page.
    var plainFetch = window.fetch;
    if (plainFetch) {
        window.fetch = function () {
            return plainFetch.apply(this, arguments).then(function (res) {
                if (res.status === 401 && res.headers.get('X-Nfelia-Locked')) lock('server');
                return res;
            });
        };
    }
    if (window.jQuery) {
        window.jQuery(document).ajaxError(function (e, xhr) {
            if (xhr && xhr.status === 401 && xhr.getResponseHeader('X-Nfelia-Locked')) lock('server');
        });
    }

    // ---------- The lock ----------
    var pkButton = dlg.querySelector('[data-lock-passkey]');
    var pwForm = dlg.querySelector('[data-lock-password]');
    var pwInput = pwForm.querySelector('input[type="password"]');
    var usePw = dlg.querySelector('[data-lock-use-password]');
    var errorBox = dlg.querySelector('[data-lock-error]');
    var offline = dlg.querySelector('[data-lock-offline]');
    var hasPasskey = pk.supported && cfg.passkeys > 0;
    var onThisDevice = hasPasskey && !!store.get(PK_KEY);

    function say(text) { errorBox.textContent = text || ''; errorBox.hidden = !text; }
    function busy(on) {
        dlg.querySelectorAll('button, input').forEach(function (el) { el.disabled = on || (navigator.onLine === false && !el.closest('.nf-lock-out')); });
    }
    function network() {
        var off = navigator.onLine === false;
        offline.hidden = !off;
        busy(false);
    }
    window.addEventListener('online', function () { if (locked) network(); });
    window.addEventListener('offline', function () { if (locked) network(); });

    function lock(reason) {
        if (locked) return;
        locked = true;
        document.querySelectorAll('dialog[open]').forEach(function (d) { if (d !== dlg) d.close(); });
        root.classList.add('nf-locked');
        if (!dlg.open) dlg.showModal();
        say('');
        pwInput.value = '';
        pkButton.hidden = !hasPasskey;
        // With a passkey here, the password waits behind "Use password instead".
        pwForm.hidden = onThisDevice;
        usePw.hidden = !onThisDevice;
        network();
        if (onThisDevice && (reason === 'return' || reason === 'load') && document.visibilityState === 'visible' && navigator.onLine !== false) {
            withPasskey();
        } else if (!onThisDevice) {
            setTimeout(function () { pwInput.focus(); }, 50);
        }
    }

    // Opened, here or in another tab.
    function opened(elsewhere) {
        locked = false;
        store.set('nf.active', String(now()));
        store.set('nf.served', String(now()));
        lastServer = now();
        fromServer = now();
        if (!elsewhere) store.set('nf.unlocked', String(now()));
        if (cfg.locked) { location.reload(); return; }   // the page was never sent: fetch it now
        dlg.close();
        root.classList.remove('nf-locked');
    }

    function failed(d) {
        if (d && d.signedOut) { location.href = signInHere(); return; }
        say(d && d.message);
    }

    function withPasskey() {
        say('');
        busy(true);
        pk.unlock().then(function (d) {
            store.set(PK_KEY, d.credentialId || '1');
            opened(false);
        }, function (err) {
            if (!pk.cancelled(err)) failed({ message: pk.message(err), signedOut: err && err.signedOut });
        }).then(function () { busy(false); });
    }

    pkButton.querySelector('[data-passkey-name]').textContent = pk.name || 'a passkey';
    pkButton.addEventListener('click', withPasskey);
    usePw.addEventListener('click', function () {
        pwForm.hidden = false;
        usePw.hidden = true;
        pwInput.focus();
    });
    pwForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!pwInput.value) { say('Enter your password.'); pwInput.focus(); return; }
        say('');
        busy(true);
        pk.api('unlock', { password: pwInput.value }).then(function (d) {
            if (d.ok) opened(false);
            else { failed(d); pwInput.select(); }
        }).then(function () { busy(false); });
    });
    // The lock cannot be closed, only opened.
    dlg.addEventListener('cancel', function (e) { e.preventDefault(); });

    // On an app start, the opening plays first, then the lock.
    function afterOpening(fn) {
        if (!root.classList.contains('nf-splashing')) return fn();
        var watch = new MutationObserver(function () {
            if (!root.classList.contains('nf-splashing')) { watch.disconnect(); fn(); }
        });
        watch.observe(root, { attributes: true, attributeFilter: ['class'] });
    }
    afterOpening(function () {
        if (cfg.locked) lock('load');
        else check('load');
    });

    // ---------- After signing in: passkey first, then the app ----------
    var offer = document.getElementById('nf-passkey');
    function offerPasskey() {
        return new Promise(function (finish) {
            if (!offer || !pk.supported || store.get(PK_KEY) || Number(store.get('nf.pk.snooze') || 0) > now()) return finish();
            pk.onDevice().then(function (ok) {
                if (!ok) return finish();
                var title = offer.querySelector('[data-pk-title]');
                if (pk.platform !== 'other') title.textContent = 'Sign in with ' + pk.name + ' next time';
                var note = offer.querySelector('[data-pk-error]');
                var go = offer.querySelector('[data-pk-go]');
                offer.addEventListener('close', function () { finish(); }, { once: true });
                offer.querySelector('[data-pk-later]').addEventListener('click', function () {
                    store.set('nf.pk.snooze', String(now() + 14 * 864e5));
                    offer.close();
                });
                go.addEventListener('click', function () {
                    note.hidden = true;
                    go.disabled = true;
                    pk.create().then(function (d) {
                        store.set(PK_KEY, d.credentialId);
                        offer.close();
                        if (window.nfToast) window.nfToast('Done. Next time, sign in with ' + pk.name + '.', 'success');
                    }, function (err) {
                        if (err && err.name === 'InvalidStateError') { store.set(PK_KEY, '1'); offer.close(); return; }
                        if (!pk.cancelled(err)) { note.textContent = pk.message(err); note.hidden = false; }
                    }).then(function () { go.disabled = false; });
                });
                if (typeof offer.showModal !== 'function') return finish();
                offer.showModal();
                // Focus the title, as every sheet does: no button looks chosen before a tap.
                var heading = document.getElementById('nf-passkey-h');
                heading.setAttribute('tabindex', '-1');
                heading.setAttribute('data-sheet-title', '');
                heading.focus({ preventScroll: true });
            });
        });
    }
    if (document.body.hasAttribute('data-just-signed-in') && !kept && !cfg.locked) {
        offerPasskey().then(function () {
            if (window.nfInstallOffer) setTimeout(window.nfInstallOffer, 400);
        });
    }

    // ---------- Settings: the person's passkeys ----------
    var card = document.querySelector('[data-passkeys]');
    if (card) {
        var list = card.querySelector('[data-pk-list]');
        var empty = card.querySelector('[data-pk-empty]');
        var add = card.querySelector('[data-pk-add]');
        var cardNote = card.querySelector('[data-pk-note]');
        var refresh = function () { empty.hidden = list.children.length > 0; };
        if (!pk.supported) {
            add.hidden = true;
            card.querySelector('[data-pk-unsupported]').hidden = false;
        }
        add.addEventListener('click', function () {
            cardNote.hidden = true;
            add.disabled = true;
            pk.create().then(function (d) {
                store.set(PK_KEY, d.credentialId);
                var li = document.createElement('li');
                li.innerHTML = list.getAttribute('data-pk-template');
                li.querySelector('b').textContent = d.passkey.name;
                li.querySelector('small').textContent = 'Added just now';
                var rm = li.querySelector('[data-pk-remove]');
                rm.setAttribute('data-pk-remove', d.passkey.id);
                rm.setAttribute('data-pk-name', d.passkey.name);
                rm.setAttribute('aria-label', 'Remove the passkey on ' + d.passkey.name);
                list.insertBefore(li, list.firstChild);
                refresh();
                cfg.passkeys++;
                hasPasskey = true;
                onThisDevice = true;
                if (window.nfToast) window.nfToast(d.message, 'success');
            }, function (err) {
                if (!pk.cancelled(err)) { cardNote.textContent = pk.message(err); cardNote.hidden = false; }
            }).then(function () { add.disabled = false; });
        });
        list.addEventListener('click', function (e) {
            var rm = e.target.closest('[data-pk-remove]');
            if (!rm) return;
            var name = rm.getAttribute('data-pk-name');
            if (!window.confirm('Remove the passkey on ' + name + '? On that device you will use your password until you add it again.')) return;
            rm.disabled = true;
            pk.api('passkey-remove', { id: Number(rm.getAttribute('data-pk-remove')) }).then(function (d) {
                if (!d.ok && d.status !== 404) { rm.disabled = false; cardNote.textContent = d.message; cardNote.hidden = false; return; }
                rm.closest('li').remove();
                refresh();
                cfg.passkeys = Math.max(0, cfg.passkeys - 1);
                if (!cfg.passkeys) { store.drop(PK_KEY); hasPasskey = onThisDevice = false; }
                if (window.nfToast) window.nfToast('Passkey removed from ' + name + '.', 'success');
            });
        });
    }
})();
