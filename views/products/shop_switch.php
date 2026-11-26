<?php
/**
 * "Show on my shop" in one tap, for the Catalogue's rows and an item's page
 * (ProductController::toggle_shop). The switch moves at once and the change
 * is sent; if it doesn't go through, it moves back and says why. Each switch
 * sits in its own small form, which also works on its own.
 */
?>
<style>
/* A row of the Catalogue: the item's link, then its shop switch */
.nf-shoprow { display: flex; align-items: center; border-top: 1px solid var(--line); }
.nf-items > .nf-shoprow:first-child { border-top: 0; }
.nf-shoprow > .nf-item { flex: 1; min-width: 0; border-top: 0; padding-right: 6px; }
.nf-shopsw { flex: none; margin: 0; padding: 0 14px 0 2px; }
.nf-shopsw label { display: grid; justify-items: center; gap: 4px; min-width: 44px; min-height: 44px; align-content: center; cursor: pointer; }
.nf-shopsw small { font: 600 .6875rem/1 var(--font-body); color: var(--ink-3); letter-spacing: .01em; }
.nf-shopsw input[role="switch"] { width: 40px; height: 24px; }
.nf-shopsw input[role="switch"]::after { top: 3px; left: 3px; width: 18px; height: 18px; }
.nf-shopsw input[role="switch"]:checked::after { transform: translateX(16px); }
.nf-shopsw input[role="switch"]:disabled { opacity: .45; cursor: default; }
.nf-shopsw input[role="switch"]:checked + small { color: var(--accent-text); }
/* On an item's page: a card of its own */
.nf-pv-shop { margin-top: 12px; padding: 4px 16px; }
.nf-pv-shop .nf-switch { min-height: 60px; }
</style>
<script>
window.nfShopSwitch = window.nfShopSwitch || function (box) {
    var form = box.form, want = box.checked;
    var fd = new FormData(form);
    fd.set('on', want ? '1' : '0');
    box.disabled = true;
    fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin',
                         headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return { message: 'You were signed out. Reload the page and sign in again.' }; }); })
        .then(function (res) {
            if (!res || !res.success) throw new Error(res && res.message ? res.message : '');
            box.checked = !!res.on_shop;
            form.elements.on.value = res.on_shop ? '0' : '1';
            if (window.nfToast) window.nfToast(res.message, 'success');
        })
        .catch(function (err) {
            box.checked = !want;
            var msg = err && err.message ? err.message : 'That didn’t go through. Check your connection and try again.';
            if (window.nfToast) window.nfToast(msg, 'error'); else alert(msg);
        })
        .finally(function () { box.disabled = false; });
};
</script>
