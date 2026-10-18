/**
 * More > Notifications (views/partials/notify_sheet.php): saves each choice
 * as it changes, turns this phone's notifications on and off with
 * assets/js/push.js, and sends a test push or email. Opens by itself at
 * /more#notifications (the bell and the emails link there).
 */
(function () {
    'use strict';

    var sheet = document.getElementById('nf-notify');
    if (!sheet) return;
    var base = window.Nfelia.url;
    var saved = sheet.querySelector('[data-saved]');
    var shown = null;

    function say(text, bad) {
        saved.textContent = text;
        saved.classList.toggle('is-bad', !!bad);
        clearTimeout(shown);
        if (!bad) shown = setTimeout(function () { saved.textContent = ''; }, 2400);
    }

    function post(action, body) {
        return fetch(base + '/api/notifications?action=' + action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.Nfelia.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().catch(function () { return { error: 'Could not reach Nfelia. Try again.' }; });
        });
    }

    // ---------- Choices ----------
    var quiet = sheet.querySelector('[data-quiet]');
    var pending = null;
    function save() {
        clearTimeout(pending);
        pending = setTimeout(function () {
            var body = { topics: {} };
            sheet.querySelectorAll('[data-topic]').forEach(function (row) {
                var t = body.topics[row.getAttribute('data-topic')] = {};
                var p = row.querySelector('[data-topic-push]'), m = row.querySelector('[data-topic-email]');
                if (p) t.push = p.checked;
                if (m) t.email = m.checked;
            });
            sheet.querySelectorAll('[data-pref]').forEach(function (el) { body[el.name] = el.type === 'checkbox' ? el.checked : el.value; });
            post('prefs', body).then(function (d) {
                if (d.success) say('Saved');
                else say(d.error || 'Not saved. Try again.', true);
            }).catch(function () { say('Not saved. Check your connection.', true); });
        }, 250);
    }
    sheet.addEventListener('change', function (e) {
        if (!e.target.matches('[data-pref], [data-topic-push], [data-topic-email]')) return;
        if (e.target.name === 'quiet_hours') quiet.hidden = !e.target.checked;
        save();
    });

    // ---------- This phone ----------
    var box = sheet.querySelector('[data-push]');
    var note = box.querySelector('[data-push-note]');
    var on = box.querySelector('[data-push-on]');
    var controls = sheet.querySelector('[data-push-controls]');
    var key = box.getAttribute('data-key');
    var push = window.NfeliaPush;
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
    var iOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    function show(state) {
        on.hidden = state !== 'off';
        controls.hidden = state !== 'on';
        note.textContent = {
            off: 'Get alerts here, even with Nfelia closed.',
            on: 'On. Alerts arrive even with Nfelia closed.',
            unavailable: 'Phone alerts are not switched on for Nfelia yet. Everything still reaches the bell.',
            ios: 'On iPhone, add Nfelia to your Home Screen first, then turn this on from there.',
            unsupported: 'This browser cannot show notifications. Everything still reaches the bell.',
            blocked: 'Notifications are blocked for Nfelia. Allow them in your browser settings, then come back.'
        }[state];
    }
    function check() {
        if (box.getAttribute('data-enabled') !== '1' || !key) return show('unavailable');
        if (!push || !push.supported()) return show(iOS && !standalone ? 'ios' : 'unsupported');
        if (window.Notification && Notification.permission === 'denied') return show('blocked');
        push.current().then(function (sub) { show(sub ? 'on' : 'off'); }, function () { show('off'); });
    }
    function busy(el, yes) { el.disabled = yes; }

    on.addEventListener('click', function () {
        busy(on, true);
        push.enable(key).then(function () { show('on'); say('Phone alerts on'); }, function (err) {
            check();
            say(err && err.message ? err.message : 'Not turned on. Try again.', true);
        }).then(function () { busy(on, false); });
    });
    sheet.querySelector('[data-push-off]').addEventListener('click', function (e) {
        busy(e.target, true);
        push.disable().then(function () { show('off'); say('Phone alerts off'); }, function () { say('Not turned off. Try again.', true); })
            .then(function () { busy(e.target, false); });
    });
    sheet.querySelectorAll('[data-test]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            busy(btn, true);
            say(btn.getAttribute('data-test') === 'email' ? 'Sending…' : 'Sending to your phones…');
            post('test', { channel: btn.getAttribute('data-test') }).then(function (d) {
                say(d.message || d.error || '', !d.success);
            }).catch(function () { say('Not sent. Check your connection.', true); })
                .then(function () { busy(btn, false); });
        });
    });

    check();
    // The bell's settings link and the emails land here. shell.js, which
    // opens sheets, runs after this file, so wait for the page to be ready.
    // The hash is then dropped, so the link works again from this page.
    function openFromLink() {
        var opener = document.querySelector('[data-open="nf-notify"]');
        if (location.hash !== '#notifications' || !opener) return;
        opener.click();
        history.replaceState(history.state, '', location.pathname + location.search);
    }
    if (document.readyState === 'complete') openFromLink();
    else document.addEventListener('DOMContentLoaded', openFromLink);
    window.addEventListener('hashchange', openFromLink);
})();
