/**
 * Passkeys in the browser: ask Nfelia for a challenge, let the phone check
 * the fingerprint, face or PIN, and send the signed answer back
 * (api/ajax/auth.php). Shared by the app (assets/js/lock.js) and the sign-in
 * page, where it also runs "Sign in with a passkey" and the passkey
 * suggestions in the email field.
 */
(function () {
    'use strict';

    var cfg = window.Nfelia || {};
    var base = cfg.url || '';

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
    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var field = document.querySelector('input[name="csrf_token"]');
        return cfg.csrf || (meta && meta.content) || (field && field.value) || '';
    }

    function api(action, body) {
        return fetch(base + '/api/auth?action=' + action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().catch(function () { return { ok: false }; }).then(function (data) {
                data.status = res.status;
                if (!data.ok && !data.message) data.message = 'Nfelia could not be reached. Check your connection and try again.';
                return data;
            });
        }, function () {
            return { ok: false, status: 0, message: 'Nfelia could not be reached. Check your connection and try again.' };
        });
    }
    function need(data) {
        if (!data.ok) throw data;
        return data;
    }

    // What the phone's prompt is called here, for buttons and titles.
    var ua = navigator.userAgent;
    var platform = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1) ? 'ios'
        : /android/i.test(ua) ? 'android'
        : /macintosh/i.test(ua) ? 'mac'
        : /windows/i.test(ua) ? 'windows' : 'other';
    var NAMES = { ios: 'Face ID or Touch ID', android: 'your fingerprint', mac: 'Touch ID', windows: 'Windows Hello', other: 'a passkey' };

    // A browser cancel, a closed prompt or a timeout: say nothing, the buttons are still there.
    function cancelled(err) {
        return err && (err.name === 'NotAllowedError' || err.name === 'AbortError');
    }
    function message(err) {
        if (err && err.name === 'InvalidStateError') return 'A passkey for your account is already on this device.';
        if (err && err.name === 'SecurityError') return 'Passkeys only work on the Nfelia site itself.';
        return (err && err.message) || 'Your phone could not confirm it is you. Try again, or use your password.';
    }

    function get(purpose, extra, mediation, signal) {
        return api('passkey-options', { purpose: purpose }).then(need).then(function (data) {
            var pk = data.options.publicKey;
            pk.challenge = toBuf(pk.challenge);
            (pk.allowCredentials || []).forEach(function (c) { c.id = toBuf(c.id); });
            var req = { publicKey: pk };
            if (mediation) req.mediation = mediation;
            if (signal) req.signal = signal;
            return navigator.credentials.get(req);
        }).then(function (cred) {
            if (!cred) throw { name: 'NotAllowedError' };
            var r = cred.response, body = extra || {};
            body.id = cred.id;
            body.clientDataJSON = toText(r.clientDataJSON);
            body.authenticatorData = toText(r.authenticatorData);
            body.signature = toText(r.signature);
            body.userHandle = r.userHandle ? toText(r.userHandle) : '';
            return api('passkey-' + purpose, body).then(need).then(function (d) { d.credentialId = cred.id; return d; });
        });
    }

    var supported = !!(window.PublicKeyCredential && navigator.credentials && window.isSecureContext);

    var nfPasskey = {
        supported: supported,
        name: NAMES[platform],
        platform: platform,
        cancelled: cancelled,
        message: message,
        api: api,
        // A fingerprint, face or PIN on this device itself.
        onDevice: function () {
            if (!supported || !PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) return Promise.resolve(false);
            return PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(function () { return false; });
        },
        create: function () {
            return api('passkey-options', { purpose: 'create' }).then(need).then(function (data) {
                var pk = data.options.publicKey;
                pk.challenge = toBuf(pk.challenge);
                pk.user.id = toBuf(pk.user.id);
                (pk.excludeCredentials || []).forEach(function (c) { c.id = toBuf(c.id); });
                return navigator.credentials.create({ publicKey: pk });
            }).then(function (cred) {
                var r = cred.response;
                return api('passkey-register', {
                    id: cred.id,
                    clientDataJSON: toText(r.clientDataJSON),
                    attestationObject: toText(r.attestationObject),
                    transports: r.getTransports ? r.getTransports() : []
                }).then(need).then(function (d) { d.credentialId = cred.id; return d; });
            });
        },
        unlock: function () { return get('unlock'); },
        login: function (remember, mediation, signal) {
            // Where to go once signed in: the sign-in page carries it (app/helpers/signin.php).
            var next = document.querySelector('[data-signin-next]');
            return get('login', { remember: !!remember, next: next ? next.value : '' }, mediation, signal);
        }
    };
    window.nfPasskey = nfPasskey;

    // ---------- The sign-in page ----------
    var box = document.querySelector('[data-passkey-signin]');
    if (!box || !supported) return;
    var button = box.querySelector('button');
    var note = box.querySelector('[role="alert"]');
    var remember = document.querySelector('input[name="remember_me"]');
    var waiting = null;
    function say(text) { note.textContent = text || ''; note.hidden = !text; }
    function done(d) { location.href = d.redirect; }

    box.hidden = false;
    button.addEventListener('click', function () {
        if (waiting) waiting.abort();
        waiting = null;
        say('');
        button.disabled = true;
        nfPasskey.login(remember && remember.checked).then(done, function (err) {
            if (!cancelled(err)) say(message(err));
        }).then(function () { button.disabled = false; });
    });
    // Offer saved passkeys right in the email field, where the browser can.
    if (PublicKeyCredential.isConditionalMediationAvailable) {
        PublicKeyCredential.isConditionalMediationAvailable().then(function (ok) {
            if (!ok || !window.AbortController) return;
            waiting = new AbortController();
            nfPasskey.login(remember && remember.checked, 'conditional', waiting.signal).then(done, function (err) {
                if (!cancelled(err)) say(message(err));
            });
        }).catch(function () {});
    }
})();
