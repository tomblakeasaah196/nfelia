/**
 * A script that breaks in someone's browser is reported to Nfelia
 * (/api/errors, then Ops → Errors), so it can be fixed before anyone has
 * to describe it. At most five a page, each once, and never in the way:
 * sent as a beacon the page does not wait for. Loaded first, on every page.
 */
(function () {
    'use strict';
    var me = document.currentScript;
    var endpoint = (me && me.getAttribute('data-endpoint')) || '/api/errors';
    var sent = 0, seen = {};

    function send(report) {
        if (sent >= 5) return;
        var key = report.message + '|' + report.file + '|' + report.line;
        if (seen[key]) return;
        seen[key] = true;
        sent++;
        report.page = location.href;
        var body = JSON.stringify(report);
        try {
            if (navigator.sendBeacon && navigator.sendBeacon(endpoint, body)) return;
            fetch(endpoint, { method: 'POST', body: body, keepalive: true, mode: 'no-cors', credentials: 'include' });
        } catch (e) { /* reporting must never break the page */ }
    }

    window.addEventListener('error', function (e) {
        if (!e.message) return;   // a picture or file that failed to load: not a script error
        send({ kind: 'error', message: String(e.message), file: e.filename || '', line: e.lineno || 0, col: e.colno || 0,
               stack: e.error && e.error.stack ? String(e.error.stack) : '' });
    });
    window.addEventListener('unhandledrejection', function (e) {
        var r = e.reason;
        if (r && (r.name === 'AbortError' || r.name === 'NotAllowedError')) return;   // a cancelled request or prompt
        // A page animation the browser dropped: the screen changed size mid-way.
        // shell.js catches these; some browser versions report them anyway.
        if (r && r.name === 'InvalidStateError' && /^Transition was aborted/.test(String(r.message))) return;
        send({ kind: 'rejection', message: r && r.message ? String(r.message) : String(r), file: '', line: 0,
               stack: r && r.stack ? String(r.stack) : '' });
    });
})();
