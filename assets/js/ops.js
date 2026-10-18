/**
 * Nfelia Ops in the browser: the phone menu, dialogs, confirmations,
 * filtering a list as you type, and passkeys (signing in, adding one).
 * Plain script, no framework: every page works without it, except passkeys.
 */
(function () {
    'use strict';

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                data.status = res.status;
                if (!data.ok && !data.message) data.message = 'Nfelia Ops could not be reached. Check your connection and try again.';
                return data;
            });
        }, function () {
            return { ok: false, status: 0, message: 'Nfelia Ops could not be reached. Check your connection and try again.' };
        });
    }

    /* ── The menu on phones ── */
    var shell = document.querySelector('[data-ops-shell]');
    var menuButton = document.querySelector('[data-ops-menu]');
    var scrim = document.querySelector('[data-ops-scrim]');
    function menu(open) {
        if (!shell) return;
        shell.classList.toggle('menu-open', open);
        if (scrim) scrim.hidden = !open;
        if (menuButton) menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { var first = shell.querySelector('.ops-side a'); if (first) first.focus(); }
        else if (menuButton) menuButton.focus();
    }
    if (menuButton) menuButton.addEventListener('click', function () { menu(!shell.classList.contains('menu-open')); });
    if (scrim) scrim.addEventListener('click', function () { menu(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && shell && shell.classList.contains('menu-open')) menu(false);
    });

    /* ── Dialogs: [data-ops-dialog=id] opens, [data-ops-close] closes ── */
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-ops-dialog]');
        if (opener) {
            var dialog = document.getElementById(opener.getAttribute('data-ops-dialog'));
            if (dialog && dialog.showModal) {
                dialog.showModal();
                var field = dialog.querySelector('input:not([type=hidden]), select, textarea');
                if (field) field.focus();
            }
            return;
        }
        var closer = e.target.closest('[data-ops-close]');
        if (closer) {
            var d = closer.closest('dialog');
            if (d) d.close();
        }
    });

    /* ── Confirm before something hard to undo; selects that save themselves ── */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var text = form.getAttribute('data-ops-confirm');
        if (text && !window.confirm(text)) {
            e.preventDefault();
            form.querySelectorAll('select[data-ops-autosubmit]').forEach(function (s) {
                var was = s.querySelector('option[selected]');
                if (was) s.value = was.value;
            });
            return;
        }
        var button = form.querySelector('button[type=submit]');
        if (button) setTimeout(function () { button.disabled = true; button.setAttribute('data-ops-busy', ''); }, 0);
    });
    // Back to a page the browser kept: its buttons work again.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) document.querySelectorAll('[data-ops-busy]').forEach(function (b) { b.disabled = false; b.removeAttribute('data-ops-busy'); });
    });
    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-ops-autosubmit]') && e.target.form) {
            e.target.form.requestSubmit ? e.target.form.requestSubmit() : e.target.form.submit();
        }
    });

    /* ── Filter a list as you type: input[data-ops-filter="rows selector"] ── */
    document.querySelectorAll('[data-ops-filter]').forEach(function (input) {
        var rows = document.querySelectorAll(input.getAttribute('data-ops-filter'));
        var table = input.closest('main') || document;
        var none = table.querySelector('[data-ops-filter-empty]');
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase(), shown = 0;
            rows.forEach(function (row) {
                if (row.hasAttribute('data-ops-filter-empty')) return;
                var hit = !q || (row.getAttribute('data-filter-text') || row.textContent.toLowerCase()).indexOf(q) !== -1;
                row.hidden = !hit;
                if (hit) shown++;
            });
            if (none) none.hidden = shown > 0;
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === '/' && !e.target.closest('input, textarea, select, [contenteditable]')) {
                e.preventDefault();
                input.focus();
            }
        });
    });

    /* ── Passkeys ── */
    function toBuf(text) {
        var s = String(text).replace(/-/g, '+').replace(/_/g, '/');
        while (s.length % 4) s += '=';
        var bin = atob(s), out = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
        return out.buffer;
    }
    function toText(buf) {
        var bytes = new Uint8Array(buf), bin = '';
        for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
    var passkeys = !!(window.PublicKeyCredential && navigator.credentials && window.isSecureContext);
    function cancelled(err) { return err && (err.name === 'NotAllowedError' || err.name === 'AbortError'); }
    function problem(err) {
        if (err && err.name === 'InvalidStateError') return 'This device already has a passkey for your Ops account.';
        if (err && err.name === 'SecurityError') return 'Passkeys only work on ops.nfelia.com itself.';
        return (err && err.message) || 'Your device could not confirm it is you. Try again.';
    }
    function need(data) { if (!data.ok) throw data; return data; }

    var signin = document.querySelector('[data-ops-passkey-signin]');
    if (signin) {
        var note = document.querySelector('[data-ops-passkey-note]');
        if (!passkeys) {
            signin.hidden = true;
        } else {
            signin.addEventListener('click', function () {
                signin.disabled = true;
                note.textContent = '';
                post(signin.getAttribute('data-options')).then(need).then(function (data) {
                    var pk = data.options.publicKey;
                    pk.challenge = toBuf(pk.challenge);
                    (pk.allowCredentials || []).forEach(function (c) { c.id = toBuf(c.id); });
                    return navigator.credentials.get({ publicKey: pk });
                }).then(function (cred) {
                    var r = cred.response;
                    return post(signin.getAttribute('data-verify'), {
                        id: cred.id,
                        clientDataJSON: toText(r.clientDataJSON),
                        authenticatorData: toText(r.authenticatorData),
                        signature: toText(r.signature),
                        userHandle: r.userHandle ? toText(r.userHandle) : ''
                    }).then(need);
                }).then(function (data) {
                    location.href = data.to;
                }).catch(function (err) {
                    signin.disabled = false;
                    if (!cancelled(err)) note.textContent = problem(err);
                });
            });
        }
    }

    var add = document.querySelector('[data-ops-passkey-add]');
    if (add) {
        var addNote = add.parentNode.querySelector('[data-ops-passkey-note]');
        if (!passkeys) {
            add.disabled = true;
            addNote.textContent = 'This browser cannot make passkeys.';
        } else {
            add.addEventListener('click', function () {
                add.disabled = true;
                addNote.textContent = '';
                post(add.getAttribute('data-options')).then(need).then(function (data) {
                    var pk = data.options.publicKey;
                    pk.challenge = toBuf(pk.challenge);
                    pk.user.id = toBuf(pk.user.id);
                    (pk.excludeCredentials || []).forEach(function (c) { c.id = toBuf(c.id); });
                    return navigator.credentials.create({ publicKey: pk });
                }).then(function (cred) {
                    var r = cred.response;
                    return post(add.getAttribute('data-save'), {
                        id: cred.id,
                        clientDataJSON: toText(r.clientDataJSON),
                        attestationObject: toText(r.attestationObject),
                        transports: r.getTransports ? r.getTransports() : []
                    }).then(need);
                }).then(function () {
                    location.reload();
                }).catch(function (err) {
                    add.disabled = false;
                    if (!cancelled(err)) addNote.textContent = problem(err);
                });
            });
        }
    }
})();
