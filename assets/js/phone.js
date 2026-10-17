/**
 * What the phone can do for Nfelia.
 *
 *   [data-share-pdf]     Share PDF: the document's PDF goes into WhatsApp, or
 *                        anywhere the phone can share a file, through the
 *                        phone's own share sheet. Shown only where the browser
 *                        can share files; the WhatsApp link and Download PDF
 *                        stay for everyone else (views/partials/share_pdf.php).
 *   [data-pick-contact]  Pick from phone contacts: fills a customer's name,
 *                        phone and email (Android Chrome). Each
 *                        data-fill-name / -phone / -email names the field to
 *                        fill, inside the button's form.
 * Loaded with `defer` by views/layouts/app.php.
 */
(function () {
    'use strict';

    // ---------- Share PDF ----------
    var probe = typeof File === 'function' ? new File(['%PDF-1.7'], 'check.pdf', { type: 'application/pdf' }) : null;
    var canShareFiles = false;
    try { canShareFiles = !!(probe && navigator.share && navigator.canShare && navigator.canShare({ files: [probe] })); } catch (e) {}

    document.querySelectorAll('[data-share-pdf]').forEach(function (btn) {
        if (!canShareFiles) return;
        btn.hidden = false;
        var label = btn.querySelector('span');
        var file = null;
        var loading = null;
        function load() {
            loading = loading || fetch(btn.getAttribute('data-url'), { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error('pdf'); return r.blob(); })
                .then(function (blob) { file = new File([blob], btn.getAttribute('data-name'), { type: 'application/pdf' }); return file; })
                .catch(function (err) { loading = null; throw err; });
            return loading;
        }
        // Some phones only share straight from the tap, so have the PDF ready
        // before it: as soon as the page settles, on a touch screen.
        if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
            var early = function () { load().catch(function () {}); };
            if (window.requestIdleCallback) window.requestIdleCallback(early, { timeout: 3000 });
            else setTimeout(early, 1200);
        }
        btn.addEventListener('pointerdown', function () { load().catch(function () {}); }, { passive: true });

        btn.addEventListener('click', function () {
            var was = label.textContent;
            var share = function (f) {
                return navigator.share({ files: [f], title: btn.getAttribute('data-title'), text: btn.getAttribute('data-text') });
            };
            var done = function () { btn.disabled = false; label.textContent = was; };
            var failed = function (err) {
                if (err && err.name === 'AbortError') return;      // closed the share sheet
                if (window.nfToast) window.nfToast(err && err.name === 'NotAllowedError'
                    ? 'The PDF is ready. Tap Share PDF again.'
                    : 'The PDF could not be shared. Use Download PDF instead.', 'error');
            };
            if (file) { share(file).catch(failed); return; }
            btn.disabled = true;
            label.textContent = 'Getting the PDF…';
            load().then(share).catch(failed).then(done, done);
        });
    });

    // ---------- Pick from phone contacts ----------
    var canPick = 'contacts' in navigator && 'ContactsManager' in window;
    document.querySelectorAll('[data-pick-contact]').forEach(function (btn) {
        if (!canPick) return;
        btn.hidden = false;
        btn.addEventListener('click', function () {
            var scope = btn.closest('form') || document;
            var field = function (which, fallback) { return scope.querySelector(btn.getAttribute('data-fill-' + which) || fallback); };
            var put = function (el, value) {
                if (!el || !value) return;
                el.value = value;
                el.dispatchEvent(new Event('input', { bubbles: true }));   // Alpine's x-model listens for this
            };
            navigator.contacts.select(['name', 'tel', 'email'], { multiple: false }).then(function (list) {
                var c = list && list[0];
                if (!c) return;
                put(field('name', '[name="full_name"]'), (c.name || [])[0]);
                put(field('phone', '[name="phone"]'), ((c.tel || [])[0] || '').replace(/[^\d+]/g, ''));
                put(field('email', '[name="email"]'), (c.email || [])[0]);
            }).catch(function () {});
        });
    });
})();
