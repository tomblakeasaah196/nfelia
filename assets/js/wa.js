/**
 * WhatsApp links open the WhatsApp app itself on a phone.
 *
 * A wa.me link tapped from the installed app, or from a page in a browser
 * tab, can land on WhatsApp's web page ("Chat on WhatsApp with…", "Open app")
 * before the app opens: the phone only hands wa.me straight to WhatsApp when
 * the tap goes there directly, and a new tab or a redirect on the way loses
 * that. So on Android and iPhone every tap on a WhatsApp link is turned into
 * the app's own address instead, with the wa.me page kept as the fallback for
 * a phone without WhatsApp. Computers keep the wa.me link as it is.
 *
 *   a[href^="https://wa.me/"]            the usual link, anywhere on the page
 *   a[href*="api.whatsapp.com/send"]     the same, the long way
 *   a[data-wa="https://wa.me/…"]         a link whose href first notes the
 *                                        message on the server (orders/wa,
 *                                        invoices/send_reminder) and then
 *                                        redirects: the note is sent in the
 *                                        background and WhatsApp opens at once
 *
 * Loaded with `defer` by every layout: the app, the shop, the public
 * documents, the website and Ops. Needs no other script.
 */
(function () {
    'use strict';

    var ua = navigator.userAgent || '';
    var android = /Android/i.test(ua);
    var ios = /iPhone|iPad|iPod/i.test(ua) || (/Macintosh/i.test(ua) && navigator.maxTouchPoints > 1);
    if (!android && !ios) return;
    // Chromium browsers on Android (Chrome, Samsung Internet, Opera, Edge…)
    // take an intent link, which falls back to wa.me on its own when no app
    // answers. Opera Mini and UC Browser are left to the plain link.
    var intents = android && /\bChrome\//.test(ua) && !/Opera Mini|UCBrowser|Firefox/i.test(ua);

    // The wa.me (or api.whatsapp.com) link behind an <a>, or null.
    function waUrl(a) {
        var href = a.getAttribute('data-wa') || a.href || '';
        if (!/^https:\/\/(wa\.me|api\.whatsapp\.com)\//i.test(href)) return null;
        try { return new URL(href); } catch (e) { return null; }
    }

    // whatsapp://send?phone=…&text=… for the link; without a number WhatsApp
    // asks who to send to, as wa.me/?text= does.
    function appQuery(url) {
        var phone = url.hostname === 'wa.me' ? url.pathname.replace(/\D/g, '') : (url.searchParams.get('phone') || '').replace(/\D/g, '');
        var text = url.searchParams.get('text');
        var q = [];
        if (phone) q.push('phone=' + phone);
        if (text) q.push('text=' + encodeURIComponent(text));
        return q.join('&');
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a) return;
        var url = waUrl(a);
        if (!url) return;
        e.preventDefault();

        // The href notes the message on the server (and would then redirect
        // to wa.me): ask it for the note only, in the background.
        if (a.hasAttribute('data-wa') && a.href !== url.href) {
            try {
                fetch(a.href, { credentials: 'same-origin', keepalive: true, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(function () {});
            } catch (err) {}
        }

        var query = appQuery(url);
        if (intents) {
            // Chrome opens the app; with none installed it goes to wa.me instead.
            location.href = 'intent://send?' + query + '#Intent;scheme=whatsapp;S.browser_fallback_url=' + encodeURIComponent(url.href) + ';end';
            return;
        }
        // The app's own address. If this page is still in front after a
        // moment, there is no WhatsApp here: the wa.me page then.
        var start = Date.now();
        var cancel = function () { clearTimeout(timer); };
        var timer = setTimeout(function () {
            document.removeEventListener('visibilitychange', cancel);
            window.removeEventListener('pagehide', cancel);
            if (!document.hidden && Date.now() - start < 2500) location.href = url.href;
        }, 1600);
        document.addEventListener('visibilitychange', cancel, { once: true });
        window.addEventListener('pagehide', cancel, { once: true });
        location.href = 'whatsapp://send?' + query;
    });
})();
