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
    var devicesBox = sheet.querySelector('[data-devices]');
    var deviceList = sheet.querySelector('[data-device-list]');
    var lastPush = sheet.querySelector('[data-last-push]');
    var key = box.getAttribute('data-key');
    var push = window.NfeliaPush;
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
    var iOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    function show(state, extra) {
        on.hidden = state !== 'off' && state !== 'reset';
        controls.hidden = state !== 'on' && state !== 'unsure';
        note.textContent = {
            off: 'Get alerts here, even with Nfelia closed.',
            reset: 'Your browser switched alerts off for this phone (it does that for sites it thinks unused). Turn them back on.',
            on: 'On. Alerts arrive even with Nfelia closed.',
            unsure: 'On here. Nfelia could not check its own copy just now; try again in a moment.',
            unavailable: 'Phone alerts are not switched on for Nfelia yet. Everything still reaches the bell.',
            ios: 'On iPhone, add Nfelia to your Home Screen first, then turn this on from there.',
            unsupported: 'This browser cannot show notifications. Everything still reaches the bell.',
            blocked: 'Notifications are blocked for Nfelia. Allow them in your browser settings, then come back.'
        }[state] + (extra ? ' ' + extra : '');
    }
    function get(action) {
        return fetch(base + '/api/notifications?action=' + action, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Nfelia-Background': '1' } })
            .then(function (r) { return r.json(); });
    }
    // Every phone with this person's alerts on, and how the last phone alert
    // went: the answer to "why didn't my phone buzz?". $mine is this phone's
    // hash, so it can be named and can't be removed from here (Turn off does that).
    function renderDevices(d, mine) {
        if (!devicesBox) return;
        var devices = d.devices || [];
        devicesBox.hidden = !devices.length && !d.last;
        deviceList.textContent = '';
        devices.forEach(function (dev) {
            var li = document.createElement('li');
            var txt = document.createElement('span');
            txt.className = 'txt';
            var b = document.createElement('b');
            b.textContent = dev.label + (mine && dev.hash === mine ? ' · this phone' : '');
            var small = document.createElement('small');
            small.textContent = (dev.last_sent ? 'Last alert ' + dev.last_sent : 'No alert sent to it yet') + ' · added ' + dev.added
                              + (dev.failures ? ' · ' + dev.failures + ' refused' : '');
            txt.appendChild(b); txt.appendChild(small);
            li.appendChild(txt);
            if (!mine || dev.hash !== mine) {
                var rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'nf-btn nf-btn-ghost';
                rm.textContent = 'Remove';
                rm.addEventListener('click', function () {
                    busy(rm, true);
                    post('device_remove', { id: dev.id }).then(function () { say('Removed'); check(); }, function () { say('Not removed. Try again.', true); busy(rm, false); });
                });
                li.appendChild(rm);
            }
            deviceList.appendChild(li);
        });
        if (lastPush) {
            lastPush.textContent = d.last ? 'Last phone alert: ' + d.last.title + ' · ' + d.last.when + ' · ' + d.last.words : '';
            lastPush.hidden = !d.last;
        }
    }
    function check() {
        if (box.getAttribute('data-enabled') !== '1' || !key) return show('unavailable');
        if (!push || !push.supported()) return show(iOS && !standalone ? 'ios' : 'unsupported');
        if (window.Notification && Notification.permission === 'denied') return show('blocked');
        // Shown as on only when this phone has a subscription AND Nfelia has
        // it saved for this person. Whatever is wrong that can be put right
        // without a tap (lost, dead, outdated) is put right first (heal).
        get('device').then(function (d) {
            if (!d || !d.success) throw new Error('no answer');
            var known = { devices: (d.devices || []).map(function (dev) { return dev.hash; }), dead: d.dead || [] };
            return push.heal(key, known).then(function (did) {
                return (did === 'ok' || did === 'nothing' ? Promise.resolve(d) : get('device').catch(function () { return d; })).then(function (d2) {
                    var known2 = { devices: (d2.devices || []).map(function (dev) { return dev.hash; }), dead: d2.dead || [] };
                    return push.state(key, known2).then(function (state) {
                        return push.current().then(function (sub) { return sub ? push.hash(sub.endpoint) : null; }).then(function (mine) {
                            show(state);
                            renderDevices(d2, mine);
                        });
                    });
                });
            });
        }).catch(function () {
            // No answer from Nfelia (offline, locked): say what this phone knows.
            push.current().then(function (sub) { show(sub ? 'unsure' : 'off'); }, function () { show('off'); });
        });
    }
    function busy(el, yes) { el.disabled = yes; }

    on.addEventListener('click', function () {
        busy(on, true);
        push.enable(key).then(function () { say('Phone alerts on'); check(); }, function (err) {
            check();
            say(err && err.message ? err.message : 'Not turned on. Try again.', true);
        }).then(function () { busy(on, false); });
    });
    sheet.querySelector('[data-push-off]').addEventListener('click', function (e) {
        busy(e.target, true);
        push.disable().then(function () { say('Phone alerts off'); check(); }, function () { say('Not turned off. Try again.', true); })
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
