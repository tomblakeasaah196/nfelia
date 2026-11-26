<?php
/**
 * "Pin to the top of my shop" in one tap, for the Catalogue's rows and an
 * item's page (ProductController::toggle_pin). The same pins as Shop
 * Studio's "Order on your shop": at most 8, the first pinned shows first.
 * The pin changes at once and the change is sent; if it doesn't go through
 * it changes back and says why. Every pin on the page then shows its place,
 * as pinning or unpinning one moves the others. Each sits in its own small
 * form, which also works without scripts.
 */
?>
<style>
.nf-pinsw { flex: none; margin: 0; padding: 0; }
.nf-pinsw--none { width: 44px; }
.nf-pinbtn { position: relative; display: grid; place-items: center; width: 44px; height: 44px; padding: 0; border: 0; border-radius: 12px; background: transparent; color: var(--ink-3); cursor: pointer; }
.nf-pinbtn svg { width: 22px; height: 22px; }
.nf-pinbtn:hover { background: rgb(31 10 20 / .045); }
.nf-pinbtn[aria-pressed="true"] { color: var(--accent-text); }
.nf-pinbtn[aria-pressed="true"] svg path:first-child { fill: currentColor; }
.nf-pinbtn small { position: absolute; top: 4px; right: 3px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px; background: var(--accent); color: var(--on-accent, #fff); font: 700 .625rem/16px var(--font-body); text-align: center; }
.nf-pinbtn small:empty { display: none; }
.nf-pinbtn:disabled { opacity: .5; cursor: default; }
/* On an item's page: a switch under Show on my shop */
.nf-pv-shop form + form { border-top: 1px solid var(--line); }
</style>
<script>
window.nfPin = window.nfPin || function (form, want) {
    var fd = new FormData(form);
    fd.set('on', want ? '1' : '0');
    var control = form.querySelector('button, input[role="switch"]');
    control.disabled = true;
    return fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin',
                                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return { message: 'You were signed out. Reload the page and sign in again.' }; }); })
        .then(function (res) {
            if (!res || !res.success) throw new Error(res && res.message ? res.message : '');
            window.nfPinPaint(res.pins || []);
            if (window.nfToast) window.nfToast(res.message, 'success');
        })
        .catch(function (err) {
            window.nfPinPaint(null);
            var msg = err && err.message ? err.message : 'That didn’t go through. Check your connection and try again.';
            if (window.nfToast) window.nfToast(msg, 'error'); else alert(msg);
        })
        .finally(function () { control.disabled = false; });
};
// Every pin on the page, from the pins first to last (null: as they were)
window.nfPinPaint = window.nfPinPaint || function (pins) {
    document.querySelectorAll('form[data-pin]').forEach(function (form) {
        var id = parseInt(form.dataset.pin, 10);
        var at = pins ? pins.indexOf(id) + 1 : parseInt(form.dataset.at || '0', 10);
        form.dataset.at = String(at);
        form.elements.on.value = at ? '0' : '1';
        var btn = form.querySelector('.nf-pinbtn');
        if (btn) {
            btn.setAttribute('aria-pressed', at ? 'true' : 'false');
            btn.querySelector('small').textContent = at ? String(at) : '';
            btn.title = at ? 'Pinned to the top of your shop, number ' + at + '. Tap to unpin.' : 'Pin to the top of your shop';
        }
        var box = form.querySelector('input[role="switch"]');
        if (box) {
            box.checked = !!at;
            var note = form.querySelector('[data-pin-note]');
            if (note) note.textContent = at ? 'Number ' + at + ' of up to 8. It shows before everything else on your shop.' : 'Show it before everything else on your shop. Up to 8 items.';
        }
    });
};
</script>
